<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'workforce') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];

// Flash messages
$success = '';
$error   = '';
if (isset($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (isset($_SESSION['flash_error']))   { $error   = $_SESSION['flash_error'];   unset($_SESSION['flash_error']);   }

// Get this workforce user's department
$selfStmt = $pdo->prepare("
    SELECT e.department_id, d.department_name, d.department_code
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$selfStmt->execute([$employeeId]);
$selfData       = $selfStmt->fetch(PDO::FETCH_ASSOC);
$department     = $selfData['department_id']   ?? null;
$departmentName = $selfData['department_name'] ?? null;
$deptCode       = $selfData['department_code'] ?? null;

$selectedEmpId = intval($_GET['selected'] ?? 0);

// ---- HANDLE ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId  = intval($_POST['employee_id'] ?? 0);
    $selEmpId  = intval($_POST['selected_employee_id'] ?? $targetId);
    $dates     = json_decode($_POST['selected_dates'] ?? '[]', true);
    $time_in   = $_POST['time_in']  ?? '';
    $time_out  = $_POST['time_out'] ?? '';
    $is_edit   = !empty($_POST['is_edit']) && $_POST['is_edit'] === '1';
    $is_overnight = $time_out < $time_in;

    if (!$department) {
        $_SESSION['flash_error'] = "Your account has no department assigned. Contact an admin.";
    } elseif (empty($dates)) {
        $_SESSION['flash_error'] = "Please select at least one date.";
    } else {
        // Verify target employee is in same department
        $deptCheck = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND department_id = ?");
        $deptCheck->execute([$targetId, $department]);

        if (!$deptCheck->fetch()) {
            $_SESSION['flash_error'] = "Invalid employee selection.";
        } elseif ($is_edit) {
            $existsStmt = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND schedule_date = ?");
            $updateStmt = $pdo->prepare("
                UPDATE schedules SET scheduled_start = ?, scheduled_end = ?, status = 'pending'
                WHERE employee_id = ? AND schedule_date = ?
            ");
            $insertStmt = $pdo->prepare("
                INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day, status)
                VALUES (?, ?, ?, ?, 0, 'pending')
            ");

            foreach ($dates as $date) {
                $startDT = $date . ' ' . $time_in  . ':00';
                $endDT   = $is_overnight
                    ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $time_out . ':00'
                    : $date . ' ' . $time_out . ':00';

                $existsStmt->execute([$targetId, $date]);
                if ($existsStmt->fetch()) {
                    $updateStmt->execute([$startDT, $endDT, $targetId, $date]);
                } else {
                    $insertStmt->execute([$targetId, $date, $startDT, $endDT]);
                }
            }
            $_SESSION['flash_success'] = count($dates) . " schedule" . (count($dates) > 1 ? "s" : "") . " re-submitted for approval.";
        } else {
            $checkStmt      = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE employee_id = ? AND schedule_date = ?");
            $duplicateDates = [];
            foreach ($dates as $date) {
                $checkStmt->execute([$targetId, $date]);
                if ($checkStmt->fetchColumn() > 0) {
                    $duplicateDates[] = date('M d, Y', strtotime($date));
                }
            }

            if (!empty($duplicateDates)) {
                $_SESSION['flash_error'] = "Schedule already exists for: " . implode(', ', $duplicateDates) . ".";
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day, status)
                    VALUES (?, ?, ?, ?, 0, 'pending')
                ");
                foreach ($dates as $date) {
                    $startDT = $date . ' ' . $time_in  . ':00';
                    $endDT   = $is_overnight
                        ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $time_out . ':00'
                        : $date . ' ' . $time_out . ':00';
                    $insertStmt->execute([$targetId, $date, $startDT, $endDT]);
                }
                $_SESSION['flash_success'] = count($dates) . " schedule" . (count($dates) > 1 ? "s" : "") . " submitted for approval.";
            }
        }
    }

    header("Location: workforce_schedule.php?selected=$selEmpId");
    exit();
}

// ---- GET DEPT EMPLOYEES ----
$deptEmployees = [];
if ($department) {
    $empStmt = $pdo->prepare("
        SELECT e.id, e.name, e.role, d.department_code
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.department_id = ?
        ORDER BY e.name
    ");
    $empStmt->execute([$department]);
    $deptEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Resolve selected employee name
$selectedEmpName = '';
if ($selectedEmpId) {
    foreach ($deptEmployees as $emp) {
        if ($emp['id'] == $selectedEmpId) { $selectedEmpName = $emp['name']; break; }
    }
}

$current_page = 'workforce_schedule';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Schedules</title>

    <link rel="stylesheet" href="../root.css">
    <link rel="stylesheet" href="../admin_pages/admin_schedule.css">
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
                    <h6 class="schedLeftTitle">
                        <?= htmlspecialchars($deptCode ?? 'Dept') ?> Employees
                    </h6>
                    <input type="text" id="empSearch" class="schedEmpSearch"
                           placeholder="Search..." oninput="filterEmployees()">
                </div>

                <?php if ($success): ?>
                    <div class="schedAlert success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="schedAlert error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!$department): ?>
                    <div style="padding:0.75rem;color:rgba(255,255,255,0.35);font-size:0.8rem;">
                        No department assigned. Contact an admin.
                    </div>
                <?php else: ?>
                <div class="schedEmpList" id="schedEmpList">
                    <?php foreach ($deptEmployees as $emp): ?>
                        <div class="schedEmpRow <?= ($emp['id'] == $selectedEmpId) ? 'active' : '' ?>"
                             data-id="<?= $emp['id'] ?>"
                             data-name="<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>"
                             onclick="selectEmployee(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['name'], ENT_QUOTES) ?>')">
                            <div class="schedEmpName"><?= htmlspecialchars($emp['name']) ?></div>
                            <div class="schedEmpMeta">
                                <span class="schedEmpRole empRole-<?= $emp['role'] ?>"><?= ucfirst($emp['role']) ?></span>
                                <?php if ($emp['department_code']): ?>
                                    <span class="schedEmpDept"><?= htmlspecialchars($emp['department_code']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($deptEmployees)): ?>
                        <div class="schedEmpEmpty">No employees in your department.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
                <form method="POST" action="workforce_schedule.php" onsubmit="return prepareSubmit()">
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
                            <i class="bi bi-send-fill"></i>
                            <span id="schedSubmitLabel">Submit for Approval</span>
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
            document.getElementById('schedPlaceholder').style.display = 'none';
            document.getElementById('schedCalContent').style.display  = 'flex';
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

            fetch(`get_workforce_schedule_calendar.php?employee_id=${currentEmpId}&year=${currentYear}&month=${currentMonth}`)
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
            document.getElementById('schedSubmitLabel').textContent = 'Submit for Approval';

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
            document.getElementById('schedSubmitLabel').textContent = 'Re-submit for Approval';

            selectedDates = [date];
            document.getElementById('schedModalOverlay').style.display = 'flex';
            setTimeout(() => {
                initDatePicker();
                if (schedDatePicker) schedDatePicker.setDate([date]);
                updateSelectedDatesList();
            }, 30);
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
