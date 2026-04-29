<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

// Flash messages
$success = '';
$error   = '';
if (isset($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (isset($_SESSION['flash_error']))   { $error   = $_SESSION['flash_error'];   unset($_SESSION['flash_error']);   }

$selectedEmpId = intval($_GET['selected'] ?? 0);

// ---- HANDLE AJAX DELETE ----
if (isset($_GET['ajax_delete'])) {
    $empId = intval($_GET['emp'] ?? 0);
    $date  = $_GET['date'] ?? '';
    if ($empId && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $pdo->prepare("DELETE FROM schedules WHERE employee_id = ? AND schedule_date = ?")
            ->execute([$empId, $date]);
        $pdo->prepare("DELETE FROM attendances WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL")
            ->execute([$empId, $date]);
    }
    exit('ok');
}

// ---- HANDLE ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id  = intval($_POST['employee_id'] ?? 0);
    $selEmpId     = intval($_POST['selected_employee_id'] ?? $employee_id);
    $dates        = json_decode($_POST['selected_dates'] ?? '[]', true);
    $time_in      = $_POST['time_in']  ?? '';
    $time_out     = $_POST['time_out'] ?? '';
    $is_edit      = !empty($_POST['is_edit']) && $_POST['is_edit'] === '1';
    $is_overnight = $time_out < $time_in;

    if (empty($dates)) {
        $_SESSION['flash_error'] = "Please select at least one date.";
    } elseif ($is_edit) {
        $existsStmt       = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND schedule_date = ?");
        $updateStmt       = $pdo->prepare("UPDATE schedules SET scheduled_start = ?, scheduled_end = ? WHERE employee_id = ? AND schedule_date = ?");
        $updateAttendance = $pdo->prepare("UPDATE attendances SET scheduled_start = ?, scheduled_end = ? WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL");
        $insertSchedule   = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day) VALUES (?, ?, ?, ?, 0)");
        $insertAttendance = $pdo->prepare("
            INSERT INTO attendances (employee_id, schedule_id, work_date, scheduled_start, scheduled_end, actual_time_in, actual_time_out, total_work_minutes, late_minutes, undertime_minutes, overtime_minutes, status, missed_time_out)
            VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, 0, 0, 0, 'incomplete', 0)
            ON DUPLICATE KEY UPDATE scheduled_start = VALUES(scheduled_start), scheduled_end = VALUES(scheduled_end)
        ");

        foreach ($dates as $date) {
            $startDT = $date . ' ' . $time_in  . ':00';
            $endDT   = $is_overnight
                ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $time_out . ':00'
                : $date . ' ' . $time_out . ':00';

            $existsStmt->execute([$employee_id, $date]);
            if ($existsStmt->fetch()) {
                $updateStmt->execute([$startDT, $endDT, $employee_id, $date]);
                $updateAttendance->execute([$startDT, $endDT, $employee_id, $date]);
            } else {
                $insertSchedule->execute([$employee_id, $date, $startDT, $endDT]);
                $schedId = $pdo->lastInsertId() ?: null;
                $insertAttendance->execute([$employee_id, $schedId, $date, $startDT, $endDT]);
            }
        }
        $_SESSION['flash_success'] = "Schedule updated successfully!";
    } else {
        $checkStmt      = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE employee_id = ? AND schedule_date = ?");
        $duplicateDates = [];
        foreach ($dates as $date) {
            $checkStmt->execute([$employee_id, $date]);
            if ($checkStmt->fetchColumn() > 0) {
                $duplicateDates[] = date('M d, Y', strtotime($date));
            }
        }

        if (!empty($duplicateDates)) {
            $_SESSION['flash_error'] = "Schedule already exists for: " . implode(', ', $duplicateDates) . ".";
        } else {
            $insertSchedule   = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day) VALUES (?, ?, ?, ?, 0)");
            $insertAttendance = $pdo->prepare("
                INSERT INTO attendances (employee_id, schedule_id, work_date, scheduled_start, scheduled_end, actual_time_in, actual_time_out, total_work_minutes, late_minutes, undertime_minutes, overtime_minutes, status, missed_time_out)
                VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, 0, 0, 0, 'incomplete', 0)
                ON DUPLICATE KEY UPDATE scheduled_start = VALUES(scheduled_start), scheduled_end = VALUES(scheduled_end)
            ");

            foreach ($dates as $date) {
                $startDT = $date . ' ' . $time_in  . ':00';
                $endDT   = $is_overnight
                    ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $time_out . ':00'
                    : $date . ' ' . $time_out . ':00';

                $insertSchedule->execute([$employee_id, $date, $startDT, $endDT]);
                $schedId = $pdo->lastInsertId() ?: null;
                $insertAttendance->execute([$employee_id, $schedId, $date, $startDT, $endDT]);
            }
            $_SESSION['flash_success'] = count($dates) . " schedule" . (count($dates) > 1 ? "s" : "") . " saved successfully!";
        }
    }

    header("Location: admin_schedule.php?selected=$selEmpId");
    exit();
}

// ---- GET ALL EMPLOYEES ----
$employees = $pdo->query("SELECT id, name, role, department_id FROM employees WHERE role IN ('employee','workforce') ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);

// Resolve selected employee name
$selectedEmpName = '';
if ($selectedEmpId) {
    foreach ($employees as $emp) {
        if ($emp['id'] == $selectedEmpId) { $selectedEmpName = $emp['name']; break; }
    }
}

$current_page = 'schedule';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Management</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="admin_schedule.css">
    <link rel="stylesheet" href="../side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>body::before { background-image: url('../images/drt_bg.jpg'); }</style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="schedWrapper">
        <div class="schedBox">

            <!-- ===== LEFT PANEL ===== -->
            <div class="schedLeftPanel">
                <div class="schedLeftHeader">
                    <h6 class="schedLeftTitle">Employees</h6>
                    <input type="text" id="empSearch" class="schedEmpSearch"
                           placeholder="Search..." oninput="filterEmployees()">
                </div>

                <?php if ($success): ?>
                    <div class="schedAlert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="schedAlert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="schedEmpList" id="schedEmpList">
                    <?php foreach ($employees as $emp): ?>
                        <div class="schedEmpRow <?= ($emp['id'] == $selectedEmpId) ? 'active' : '' ?>"
                             data-id="<?= $emp['id'] ?>"
                             data-name="<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>"
                             onclick="selectEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')">
                            <div class="schedEmpName"><?= htmlspecialchars($emp['name']) ?></div>
                            <div class="schedEmpMeta">
                                <span class="schedEmpRole empRole-<?= $emp['role'] ?>"><?= ucfirst($emp['role']) ?></span>
                                <?php if ($emp['department_id']): ?>
                                    <span class="schedEmpDept"><?= htmlspecialchars($emp['department_id']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                        <div class="schedEmpEmpty">No employees found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== DIVIDER ===== -->
            <div class="schedPanelDivider"></div>

            <!-- ===== RIGHT PANEL ===== -->
            <div class="schedRightPanel">

                <!-- Placeholder -->
                <div class="schedPlaceholder" id="schedPlaceholder">
                    <i class="bi bi-calendar2-week schedPlaceholderIcon"></i>
                    <p>Select an employee to view their schedule</p>
                </div>

                <!-- Calendar content -->
                <div class="schedCalContent" id="schedCalContent" style="display:none;">

                    <div class="schedCalHeader">
                        <h6 class="schedCalTitle">Schedule for <span id="selectedEmpName"><?= htmlspecialchars($selectedEmpName) ?></span></h6>
                        <div style="display:flex;align-items:center;gap:0.5rem;">
                            <button class="schedNavBtn" onclick="changeMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                            <span class="schedMonthLabel" id="schedMonthLabel"></span>
                            <button class="schedNavBtn" onclick="changeMonth(1)"><i class="bi bi-chevron-right"></i></button>
                            <button class="schedAddBtn" onclick="openAddModal()">
                                <i class="bi bi-plus-lg"></i> Add Schedule
                            </button>
                        </div>
                    </div>

                    <div class="schedCalContainer" id="schedCalContainer">
                        <!-- AJAX loaded -->
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Add / Edit Modal -->
    <div class="schedModalOverlay" id="schedModalOverlay" style="display:none;" onclick="closeModal(event)">
        <div class="schedModal">
            <div class="schedModalHeader">
                <h6 id="schedModalTitle">Add Schedule</h6>
                <button onclick="closeModalBtn()"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="schedModalBody">
                <form method="POST" action="admin_schedule.php" onsubmit="return prepareSubmit()">
                    <input type="hidden" name="employee_id"          id="modalEmpId">
                    <input type="hidden" name="selected_employee_id" id="modalSelEmpId">
                    <input type="hidden" name="selected_dates"       id="selectedDatesInput">
                    <input type="hidden" name="is_edit"              id="isEditMode" value="0">

                    <div class="formGroup" style="margin-bottom:1rem;">
                        <label>Employee</label>
                        <input type="text" id="modalEmpName" class="formControl" readonly
                               style="opacity:0.6;cursor:default;">
                    </div>

                    <div class="formGrid">
                        <div class="formGroup">
                            <label>Time In</label>
                            <input type="time" name="time_in" id="modalTimeIn" class="formControl" required>
                        </div>
                        <div class="formGroup">
                            <label>Time Out <small class="nightShiftHint">(next day if night shift)</small></label>
                            <input type="time" name="time_out" id="modalTimeOut" class="formControl" required>
                        </div>
                    </div>

                    <div class="formGroup" style="margin-top:1rem;">
                        <label>Select Dates</label>
                        <p class="formHint">Click dates to select work days. Click again to deselect.</p>
                        <input type="text" id="schedDatePicker" class="formControl"
                               placeholder="Click to select dates..." readonly>
                        <div id="selectedDatesList" class="selectedDatesList"></div>
                    </div>

                    <div class="formActions">
                        <button type="submit" class="btnSave">
                            <i class="bi bi-check-circle-fill"></i>
                            <span id="schedSubmitLabel">Save Schedule</span>
                        </button>
                        <button type="button" class="btnCancel" onclick="closeModalBtn()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        let currentEmpId   = <?= $selectedEmpId ?: 'null' ?>;
        let currentEmpName = <?= json_encode($selectedEmpName) ?>;
        let currentYear    = <?= date('Y') ?>;
        let currentMonth   = <?= date('n') ?>;
        let schedDatePicker = null;
        let selectedDates   = [];

        // Auto-select on page load (e.g. after form submit redirect)
        if (currentEmpId) {
            showCalendarPanel();
            updateMonthLabel();
            loadCalendar();
        }

        // ---- EMPLOYEE FILTER ----
        function filterEmployees() {
            const q = document.getElementById('empSearch').value.toLowerCase();
            document.querySelectorAll('#schedEmpList .schedEmpRow').forEach(row => {
                row.style.display = row.dataset.name.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        // ---- SELECT EMPLOYEE ----
        function selectEmployee(id, name) {
            currentEmpId   = id;
            currentEmpName = name;
            currentYear    = new Date().getFullYear();
            currentMonth   = new Date().getMonth() + 1;

            document.querySelectorAll('#schedEmpList .schedEmpRow').forEach(r => r.classList.remove('active'));
            document.querySelector(`#schedEmpList .schedEmpRow[data-id="${id}"]`).classList.add('active');
            document.getElementById('selectedEmpName').textContent = name;

            showCalendarPanel();
            updateMonthLabel();
            loadCalendar();
        }

        function showCalendarPanel() {
            document.getElementById('schedPlaceholder').style.display  = 'none';
            document.getElementById('schedCalContent').style.display   = 'flex';
        }

        // ---- MONTH NAVIGATION ----
        function changeMonth(dir) {
            currentMonth += dir;
            if (currentMonth < 1)  { currentMonth = 12; currentYear--; }
            if (currentMonth > 12) { currentMonth = 1;  currentYear++; }
            updateMonthLabel();
            loadCalendar();
        }

        function updateMonthLabel() {
            const months = ['January','February','March','April','May','June',
                            'July','August','September','October','November','December'];
            document.getElementById('schedMonthLabel').textContent = months[currentMonth - 1] + ' ' + currentYear;
        }

        // ---- LOAD CALENDAR (AJAX) ----
        function loadCalendar() {
            if (!currentEmpId) return;
            const c = document.getElementById('schedCalContainer');
            c.innerHTML = '<div class="schedCalLoading"><div class="schedSpinner"></div> Loading...</div>';

            fetch(`get_admin_schedule_calendar.php?employee_id=${currentEmpId}&year=${currentYear}&month=${currentMonth}`)
                .then(r => r.text())
                .then(html => { c.innerHTML = html; })
                .catch(() => { c.innerHTML = '<div class="schedCalEmpty" style="color:#ff8a8a;">Failed to load.</div>'; });
        }

        // ---- MODAL: ADD ----
        function openAddModal() {
            document.getElementById('schedModalTitle').textContent  = 'Add Schedule';
            document.getElementById('modalEmpId').value             = currentEmpId;
            document.getElementById('modalSelEmpId').value          = currentEmpId;
            document.getElementById('modalEmpName').value           = currentEmpName;
            document.getElementById('modalTimeIn').value            = '';
            document.getElementById('modalTimeOut').value           = '';
            document.getElementById('isEditMode').value             = '0';
            document.getElementById('schedSubmitLabel').textContent = 'Save Schedule';

            selectedDates = [];
            document.getElementById('schedModalOverlay').style.display = 'flex';
            setTimeout(() => { initDatePicker(); updateSelectedDatesList(); }, 30);
        }

        // ---- MODAL: EDIT (called from calendar HTML) ----
        function openEditModal(empId, date, timeIn, timeOut) {
            document.getElementById('schedModalTitle').textContent  = 'Edit Schedule';
            document.getElementById('modalEmpId').value             = empId;
            document.getElementById('modalSelEmpId').value          = currentEmpId;
            document.getElementById('modalEmpName').value           = currentEmpName;
            document.getElementById('modalTimeIn').value            = timeIn;
            document.getElementById('modalTimeOut').value           = timeOut;
            document.getElementById('isEditMode').value             = '1';
            document.getElementById('schedSubmitLabel').textContent = 'Update Schedule';

            selectedDates = [date];
            document.getElementById('schedModalOverlay').style.display = 'flex';
            setTimeout(() => {
                initDatePicker();
                if (schedDatePicker) schedDatePicker.setDate([date]);
                updateSelectedDatesList();
            }, 30);
        }

        // ---- DELETE (AJAX, called from calendar HTML) ----
        async function deleteScheduleDay(empId, date) {
            if (!confirm('Delete schedule for ' + date + '?')) return;
            await fetch(`admin_schedule.php?ajax_delete=1&emp=${empId}&date=${date}`);
            loadCalendar();
        }

        // ---- DATE PICKER ----
        function initDatePicker() {
            if (schedDatePicker) { schedDatePicker.destroy(); schedDatePicker = null; }
            schedDatePicker = flatpickr('#schedDatePicker', {
                mode:        'multiple',
                dateFormat:  'Y-m-d',
                altInput:    true,
                altFormat:   'M j, Y',
                conjunction: ', ',
                onChange(dates) {
                    selectedDates = dates.map(d => {
                        const y   = d.getFullYear();
                        const m   = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        return `${y}-${m}-${day}`;
                    });
                    updateSelectedDatesList();
                }
            });
        }

        function updateSelectedDatesList() {
            const list = document.getElementById('selectedDatesList');
            if (selectedDates.length === 0) {
                list.innerHTML = '<p class="noDateSelected">No dates selected.</p>';
                return;
            }
            const fmt = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
            });
            list.innerHTML = selectedDates.map(d => `
                <span class="selectedDateTag">
                    ${fmt(d)}
                    <span onclick="removeDate('${d}')" class="selectedDateRemove">✕</span>
                </span>
            `).join('');
        }

        function removeDate(dateStr) {
            selectedDates = selectedDates.filter(d => d !== dateStr);
            if (schedDatePicker) schedDatePicker.setDate(selectedDates);
            updateSelectedDatesList();
        }

        function prepareSubmit() {
            if (!document.getElementById('modalEmpId').value) {
                alert('No employee selected.'); return false;
            }
            if (selectedDates.length === 0) {
                alert('Please select at least one date.'); return false;
            }
            if (!document.getElementById('modalTimeIn').value || !document.getElementById('modalTimeOut').value) {
                alert('Please enter time in and time out.'); return false;
            }
            document.getElementById('selectedDatesInput').value = JSON.stringify(selectedDates);
            return true;
        }

        function closeModal(e) {
            if (e.target === document.getElementById('schedModalOverlay')) closeModalBtn();
        }
        function closeModalBtn() {
            document.getElementById('schedModalOverlay').style.display = 'none';
        }

        // Auto-dismiss flash alerts
        setTimeout(() => {
            document.querySelectorAll('.schedAlert').forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity    = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 3000);

    </script>
</body>
</html>
