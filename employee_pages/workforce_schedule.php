<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'workforce') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];
$success    = "";
$error      = "";

// Get this workforce user's department
$selfStmt = $pdo->prepare("
    SELECT e.department_id, d.department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$selfStmt->execute([$employeeId]);
$selfData       = $selfStmt->fetch(PDO::FETCH_ASSOC);
$department     = $selfData['department_id']   ?? null;
$departmentName = $selfData['department_name'] ?? null;

// ---- HANDLE SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId   = $_POST['employee_id'] ?? '';
    $dates      = json_decode($_POST['selected_dates'] ?? '[]', true);
    $time_in    = $_POST['time_in']  ?? '';
    $time_out   = $_POST['time_out'] ?? '';
    $is_overnight = $time_out < $time_in;

    if (empty($targetId) || empty($dates) || !$time_in || !$time_out) {
        $error = "Please fill in all fields and select at least one date.";
    } elseif (!$department) {
        $error = "Your account has no department assigned. Contact an admin.";
    } else {
        // Verify target employee is in same department
        $deptCheck = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND department_id = ?");
        $deptCheck->execute([$targetId, $department]);

        if (!$deptCheck->fetch()) {
            $error = "Invalid employee selection.";
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
                $error = "Schedule already exists for: " . implode(', ', $duplicateDates) . ".";
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

                $success = count($dates) . " schedule" . (count($dates) > 1 ? 's' : '') . " submitted for admin approval.";
            }
        }
    }
}

// Get employees in same department (all roles except self)
$deptEmployees = [];
if ($department) {
    $empStmt = $pdo->prepare("
        SELECT id, name FROM employees
        WHERE department_id = ? AND id != ?
        ORDER BY name
    ");
    $empStmt->execute([$department, $employeeId]);
    $deptEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all pending + recently approved schedules for dept employees
$deptSchedules = [];
if ($department) {
    $schedStmt = $pdo->prepare("
        SELECT
            s.id,
            e.name   AS employee_name,
            s.schedule_date,
            s.scheduled_start,
            s.scheduled_end,
            s.status
        FROM schedules s
        JOIN employees e ON s.employee_id = e.id
        WHERE e.department_id = ?
          AND s.is_rest_day = 0
          AND (s.status = 'pending' OR (s.status IN ('approved','rejected') AND s.schedule_date >= CURDATE() - INTERVAL 7 DAY))
        ORDER BY s.status ASC, s.schedule_date ASC, e.name
    ");
    $schedStmt->execute([$department]);
    $deptSchedules = $schedStmt->fetchAll(PDO::FETCH_ASSOC);
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

    <style>
        body::before { background-image: url('../images/drt_bg.jpg'); }

        .wf-dept-badge {
            background: rgba(151, 190, 65, 0.15);
            border: 1px solid rgba(151, 190, 65, 0.3);
            color: #97be41;
            border-radius: 20px;
            padding: 0.15rem 0.7rem;
            font-size: 0.75rem;
        }

        .status-pending  { color: #f0ad4e; font-weight: 600; font-size: 0.8rem; }
        .status-approved { color: #97be41; font-weight: 600; font-size: 0.8rem; }
        .status-rejected { color: #ff8a8a; font-weight: 600; font-size: 0.8rem; }

        .no-dept-warning {
            background: rgba(220, 53, 69, 0.12);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 10px;
            padding: 1rem 1.2rem;
            color: #ff8a8a;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <div class="scheduleWrapper">
        <div class="scheduleBox">

            <!-- TITLE ROW -->
            <div class="adminTitleRow">
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <h5 class="adminTitle">Manage Schedules</h5>
                    <?php if ($department): ?>
                        <span class="wf-dept-badge"><?= htmlspecialchars($departmentName ?? $department) ?> Department</span>
                    <?php endif; ?>
                </div>
                <input type="text" id="searchInput" class="searchInput" placeholder="Search schedules..." onkeyup="searchTable()">
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$department): ?>
                <div class="no-dept-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Your account has no department assigned. Contact an admin to set your department before you can manage schedules.
                </div>
            <?php else: ?>

            <!-- DEPT SCHEDULES TABLE -->
            <div class="tableScrollWrapper">
                <table class="table table-bordered table-hover mt-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($deptSchedules) > 0): ?>
                            <?php foreach ($deptSchedules as $row): ?>
                                <?php
                                    $startDT      = $row['scheduled_start'];
                                    $endDT        = $row['scheduled_end'];
                                    $startHour    = $startDT ? (int)date('H', strtotime($startDT)) : 6;
                                    $isNightShift = ($startHour >= 18 || $startHour < 6);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['schedule_date'])) ?></td>
                                    <td><?= $startDT ? date('h:i A', strtotime($startDT)) : '—' ?></td>
                                    <td><?= $endDT   ? date('h:i A', strtotime($endDT))   : '—' ?></td>
                                    <td>
                                        <?php if ($isNightShift): ?>
                                            <span class="badge nightShiftBadge">Night Shift</span>
                                        <?php else: ?>
                                            <span class="badge dayShiftBadge">Day Shift</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <span class="status-pending"><i class="bi bi-hourglass-split"></i> Pending</span>
                                        <?php elseif ($row['status'] === 'rejected'): ?>
                                            <span class="status-rejected"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                        <?php else: ?>
                                            <span class="status-approved"><i class="bi bi-check-circle-fill"></i> Approved</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center" style="color:rgba(255,255,255,0.4);">No schedules found for your department.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PROPOSE SCHEDULE FORM -->
            <div class="adminFormWrapper">
                <h6 class="formTitle">Propose New Schedule</h6>
                <p style="color:rgba(255,255,255,0.4);font-size:0.8rem;margin-bottom:1rem;">
                    Schedules are submitted for admin approval before taking effect.
                </p>
                <form method="POST" action="/DTR-Internship-Project/employee_pages/workforce_schedule.php" onsubmit="return prepareSubmit()">

                    <input type="hidden" name="employee_id"    id="employeeSelect">
                    <input type="hidden" name="selected_dates" id="selectedDatesInput">

                    <div class="formGrid">

                        <!-- Employee Search -->
                        <div class="formGroup" style="position:relative;">
                            <label>Employee (<?= htmlspecialchars($departmentName ?? $department) ?> Dept.)</label>
                            <input type="text" id="employeeSearch" class="formControl"
                                placeholder="Type to search..." autocomplete="off"
                                oninput="filterEmployees()">
                            <div id="employeeDropdown" class="employeeDropdown">
                                <?php foreach ($deptEmployees as $emp): ?>
                                    <div class="employeeOption"
                                        data-id="<?= $emp['id'] ?>"
                                        data-name="<?= htmlspecialchars($emp['name']) ?>"
                                        onclick="selectEmployee(this)">
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($deptEmployees)): ?>
                                    <div class="employeeOption" style="color:#aaa;cursor:default;">No employees in your department.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Time In -->
                        <div class="formGroup">
                            <label>Time In</label>
                            <input type="time" name="time_in" id="timeIn" class="formControl" required>
                        </div>

                        <!-- Time Out -->
                        <div class="formGroup">
                            <label>Time Out <small class="nightShiftHint">(next day if night shift)</small></label>
                            <input type="time" name="time_out" id="timeOut" class="formControl" required>
                        </div>

                    </div>

                    <!-- Date Picker -->
                    <div class="formGroup" style="margin-top:1rem;">
                        <label>Select Dates</label>
                        <p class="formHint">Click dates to select work days. Click again to deselect.</p>
                        <input type="text" id="scheduleDatePicker" class="formControl" placeholder="Click to select dates..." readonly>
                        <div id="selectedDatesList" class="selectedDatesList"></div>
                    </div>

                    <!-- Submit -->
                    <div class="formActions">
                        <button type="submit" class="btnSave">
                            <i class="bi bi-send-fill"></i> Submit for Approval
                        </button>
                    </div>

                </form>
            </div>

            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // ---- FLATPICKR ----
        let selectedDates = [];

        const fp = flatpickr('#scheduleDatePicker', {
            mode:        'multiple',
            dateFormat:  'Y-m-d',
            altInput:    true,
            altFormat:   'M j, Y',
            conjunction: ', ',
            onChange: function(dates) {
                selectedDates = dates.map(d => {
                    const y   = d.getFullYear();
                    const m   = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${y}-${m}-${day}`;
                });
                updateSelectedDatesList();
            }
        });

        function updateSelectedDatesList() {
            const list = document.getElementById('selectedDatesList');
            if (selectedDates.length === 0) {
                list.innerHTML = '<p class="noDateSelected">No dates selected.</p>';
                return;
            }
            const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
            });
            list.innerHTML = selectedDates.map(d => `
                <span class="selectedDateTag">
                    ${fmtDate(d)}
                    <span onclick="removeDate('${d}')" class="selectedDateRemove">✕</span>
                </span>
            `).join('');
        }

        function removeDate(dateStr) {
            selectedDates = selectedDates.filter(d => d !== dateStr);
            fp.setDate(selectedDates);
            updateSelectedDatesList();
        }

        // ---- PREPARE SUBMIT ----
        function prepareSubmit() {
            if (!document.getElementById('employeeSelect').value) {
                alert('Please select an employee.');
                return false;
            }
            if (selectedDates.length === 0) {
                alert('Please select at least one date.');
                return false;
            }
            if (!document.getElementById('timeIn').value || !document.getElementById('timeOut').value) {
                alert('Please enter time in and time out.');
                return false;
            }
            document.getElementById('selectedDatesInput').value = JSON.stringify(selectedDates);
            return true;
        }

        // ---- EMPLOYEE SEARCH ----
        function filterEmployees() {
            const input    = document.getElementById('employeeSearch').value.toLowerCase();
            const dropdown = document.getElementById('employeeDropdown');
            const options  = document.querySelectorAll('.employeeOption[data-id]');

            dropdown.style.display = input === '' ? 'none' : 'block';
            options.forEach(opt => {
                opt.style.display = opt.getAttribute('data-name').toLowerCase().includes(input) ? 'block' : 'none';
            });
            document.getElementById('employeeSelect').value = '';
        }

        function selectEmployee(el) {
            document.getElementById('employeeSearch').value           = el.getAttribute('data-name');
            document.getElementById('employeeSelect').value           = el.getAttribute('data-id');
            document.getElementById('employeeDropdown').style.display = 'none';
        }

        // ---- SEARCH TABLE ----
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.tableScrollWrapper tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        // ---- CLOSE DROPDOWNS ON OUTSIDE CLICK ----
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#employeeSearch') && !e.target.closest('#employeeDropdown')) {
                const dd = document.getElementById('employeeDropdown');
                if (dd) dd.style.display = 'none';
            }
        });

        // ---- AUTO DISMISS ALERTS ----
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity    = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 3000);

    </script>
</body>
</html>
