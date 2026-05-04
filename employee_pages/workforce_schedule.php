<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication + role check
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId     = $_POST['employee_id']    ?? '';
    $dates        = json_decode($_POST['selected_dates'] ?? '[]', true);
    $timeIn       = $_POST['time_in']        ?? '';
    $timeOut      = $_POST['time_out']       ?? '';
    $isOvernight  = $timeOut < $timeIn;

    if (empty($targetId) || empty($dates) || !$timeIn || !$timeOut) {
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
                    $startDT = $date . ' ' . $timeIn  . ':00';
                    $endDT   = $isOvernight
                        ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $timeOut . ':00'
                        : $date . ' ' . $timeOut . ':00';

                    $insertStmt->execute([$targetId, $date, $startDT, $endDT]);
                }

                $success = count($dates) . " schedule" . (count($dates) > 1 ? 's' : '') . " submitted for admin approval.";
            }
        }
    }
}

// Get employees in same department (excluding self)
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

// Get pending + recently approved/rejected schedules for dept employees
$deptSchedules = [];
if ($department) {
    $schedStmt = $pdo->prepare("
        SELECT
            s.id,
            e.name AS employee_name,
            s.schedule_date,
            s.scheduled_start,
            s.scheduled_end,
            s.status
        FROM schedules s
        JOIN employees e ON s.employee_id = e.id
        WHERE e.department_id = ?
          AND s.is_rest_day = 0
          AND (
              s.status = 'pending'
              OR (s.status IN ('approved','rejected') AND s.schedule_date >= CURDATE() - INTERVAL 7 DAY)
          )
        ORDER BY s.status ASC, s.schedule_date ASC, e.name
    ");
    $schedStmt->execute([$department]);
    $deptSchedules = $schedStmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentPage = 'workforce_schedule';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules</title>

    <!-- CSS Load Order: root → typography → components → navbars → page -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">
    <link rel="stylesheet" href="../admin_pages/admin_schedule.css">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>

    <?php include '../sidebar_revised.php'; ?>

    <div id="main-wrapper">
        <?php include '../topbar_revised.php'; ?>

        <div class="card card-glass logs-card">
            <div class="card-body d-flex flex-column logs-card-body">

                <!-- Title Row -->
                <div class="admin-title-row">
                    <div class="admin-title-left">
                        <h5 class="admin-title section-title">Manage Schedules</h5>
                        <?php if ($department): ?>
                            <span class="dept-badge">
                                <?= htmlspecialchars($departmentName ?? $department) ?> Department
                            </span>
                        <?php endif; ?>
                    </div>
                    <input type="text" id="search-input" class="search-input" placeholder="Search schedules..." onkeyup="searchTable()">
                </div>

                <!-- Alerts -->
                <?php if ($success): ?>
                    <div class="alert alert-success text-meta"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger text-meta"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!$department): ?>
                    <div class="no-dept-warning text-meta">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Your account has no department assigned. Contact an admin to set your department before you can manage schedules.
                    </div>
                <?php else: ?>

                <!-- Department Schedules Table -->
                <div class="table-scroll-wrapper">
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
                                        <td class="text-secondary"><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td class="text-secondary"><?= date('M d, Y', strtotime($row['schedule_date'])) ?></td>
                                        <td class="text-secondary"><?= $startDT ? date('h:i A', strtotime($startDT)) : '—' ?></td>
                                        <td class="text-secondary"><?= $endDT   ? date('h:i A', strtotime($endDT))   : '—' ?></td>
                                        <td>
                                            <?php if ($isNightShift): ?>
                                                <span class="badge night-shift-badge">Night Shift</span>
                                            <?php else: ?>
                                                <span class="badge day-shift-badge">Day Shift</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <span class="status-pending text-meta">
                                                    <i class="bi bi-hourglass-split"></i> Pending
                                                </span>
                                            <?php elseif ($row['status'] === 'rejected'): ?>
                                                <span class="status-rejected text-meta">
                                                    <i class="bi bi-x-circle-fill"></i> Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="status-approved text-meta">
                                                    <i class="bi bi-check-circle-fill"></i> Approved
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-meta">
                                        No schedules found for your department.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Propose Schedule Form -->
                <div class="admin-form-wrapper">
                    <h6 class="form-title subsection-title">Propose New Schedule</h6>
                    <p class="text-meta" style="margin-bottom:1rem;">
                        Schedules are submitted for admin approval before taking effect.
                    </p>

                    <form method="POST" action="/DTR-Internship-Project/employee_pages/workforce_schedule.php" onsubmit="return prepareSubmit()">

                        <input type="hidden" name="employee_id"    id="employee-select">
                        <input type="hidden" name="selected_dates" id="selected-dates-input">

                        <div class="form-grid">

                            <!-- Employee Search -->
                            <div class="form-group" style="position: relative;">
                                <label class="text-secondary">
                                    Employee (<?= htmlspecialchars($departmentName ?? $department) ?> Dept.)
                                </label>
                                <input
                                    type="text"
                                    id="employee-search"
                                    class="form-control-custom"
                                    placeholder="Type to search..."
                                    autocomplete="off"
                                    oninput="filterEmployees()"
                                >
                                <div id="employee-dropdown" class="employee-dropdown">
                                    <?php foreach ($deptEmployees as $emp): ?>
                                        <div class="employee-option text-secondary"
                                             data-id="<?= $emp['id'] ?>"
                                             data-name="<?= htmlspecialchars($emp['name']) ?>"
                                             onclick="selectEmployee(this)">
                                            <?= htmlspecialchars($emp['name']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($deptEmployees)): ?>
                                        <div class="employee-option text-meta" style="cursor: default;">
                                            No employees in your department.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Time In -->
                            <div class="form-group">
                                <label class="text-secondary">Time In</label>
                                <input type="time" name="time_in" id="time-in" class="form-control-custom" required>
                            </div>

                            <!-- Time Out -->
                            <div class="form-group">
                                <label class="text-secondary">
                                    Time Out <small class="night-shift-hint text-meta">(next day if night shift)</small>
                                </label>
                                <input type="time" name="time_out" id="time-out" class="form-control-custom" required>
                            </div>

                        </div>

                        <!-- Date Picker -->
                        <div class="form-group" style="margin-top: 1rem;">
                            <label class="text-secondary">Select Dates</label>
                            <p class="text-meta">Click dates to select work days. Click again to deselect.</p>
                            <input type="text" id="schedule-date-picker" class="form-control-custom" placeholder="Click to select dates..." readonly>
                            <div id="selected-dates-list" class="selected-dates-list"></div>
                        </div>

                        <!-- Submit -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-send-fill"></i> Submit for Approval
                            </button>
                        </div>

                    </form>
                </div>

                <?php endif; ?>

            </div>
        </div>

    </div><!-- #main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /* ================================================
           FLATPICKR
           ================================================ */
        let selectedDates = [];

        const fp = flatpickr('#schedule-date-picker', {
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

        function updateSelectedDatesList() {
            const list = document.getElementById('selected-dates-list');
            if (selectedDates.length === 0) {
                list.innerHTML = '<p class="text-meta">No dates selected.</p>';
                return;
            }
            const fmtDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
            });
            list.innerHTML = selectedDates.map(d => `
                <span class="selected-date-tag text-meta">
                    ${fmtDate(d)}
                    <span onclick="removeDate('${d}')" class="selected-date-remove">✕</span>
                </span>
            `).join('');
        }

        function removeDate(dateStr) {
            selectedDates = selectedDates.filter(d => d !== dateStr);
            fp.setDate(selectedDates);
            updateSelectedDatesList();
        }

        /* ================================================
           FORM SUBMIT
           ================================================ */
        function prepareSubmit() {
            if (!document.getElementById('employee-select').value) {
                alert('Please select an employee.');
                return false;
            }
            if (selectedDates.length === 0) {
                alert('Please select at least one date.');
                return false;
            }
            if (!document.getElementById('time-in').value || !document.getElementById('time-out').value) {
                alert('Please enter time in and time out.');
                return false;
            }
            document.getElementById('selected-dates-input').value = JSON.stringify(selectedDates);
            return true;
        }

        /* ================================================
           EMPLOYEE SEARCH
           ================================================ */
        function filterEmployees() {
            const input    = document.getElementById('employee-search').value.toLowerCase();
            const dropdown = document.getElementById('employee-dropdown');
            const options  = document.querySelectorAll('.employee-option[data-id]');

            dropdown.style.display = input === '' ? 'none' : 'block';
            options.forEach(opt => {
                opt.style.display = opt.getAttribute('data-name').toLowerCase().includes(input) ? 'block' : 'none';
            });
            document.getElementById('employee-select').value = '';
        }

        function selectEmployee(el) {
            document.getElementById('employee-search').value           = el.getAttribute('data-name');
            document.getElementById('employee-select').value           = el.getAttribute('data-id');
            document.getElementById('employee-dropdown').style.display = 'none';
        }

        /* ================================================
           TABLE SEARCH
           ================================================ */
        function searchTable() {
            const input = document.getElementById('search-input').value.toLowerCase();
            document.querySelectorAll('.table-scroll-wrapper tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        /* ================================================
           CLOSE DROPDOWNS ON OUTSIDE CLICK
           ================================================ */
        document.addEventListener('click', e => {
            if (!e.target.closest('#employee-search') && !e.target.closest('#employee-dropdown')) {
                const dd = document.getElementById('employee-dropdown');
                if (dd) dd.style.display = 'none';
            }
        });

        /* ================================================
           AUTO DISMISS ALERTS
           ================================================ */
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