<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$success = "";
$error   = "";

// ---- HANDLE DELETE ----
if (isset($_GET['delete'], $_GET['date'])) {
    $employeeId = $_GET['delete'];
    $date       = $_GET['date'];

    $pdo->prepare("DELETE FROM schedules WHERE employee_id = ? AND schedule_date = ?")
        ->execute([$employeeId, $date]);

    // Also delete the attendance row for this schedule
    // Only delete if no actual time-in has been recorded yet
    // If the employee already timed in, preserve the attendance record
    $pdo->prepare("
        DELETE FROM attendances 
        WHERE employee_id = ? 
        AND work_date = ?
        AND actual_time_in IS NULL
    ")->execute([$employeeId, $date]);

    $success = "Schedule deleted successfully!";
}

// ---- HANDLE ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id  = $_POST['employee_id'];
    $dates        = json_decode($_POST['selected_dates'], true);
    $time_in      = $_POST['time_in'];
    $time_out     = $_POST['time_out'];
    $is_edit      = !empty($_POST['is_edit']) && $_POST['is_edit'] === '1';
    $is_overnight = $time_out < $time_in;

    if (empty($dates)) {
        $error = "Please select at least one date.";
    } elseif ($is_edit) {
        $updateStmt = $pdo->prepare("
            UPDATE schedules
            SET scheduled_start = ?, scheduled_end = ?
            WHERE employee_id = ? AND schedule_date = ?
        ");
        $updateAttendance = $pdo->prepare("
            UPDATE attendances
            SET scheduled_start = ?, scheduled_end = ?
            WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL
        ");
        foreach ($dates as $date) {
            $startDatetime = $date . ' ' . $time_in . ':00';
            $endDatetime   = $is_overnight
                ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $time_out . ':00'
                : $date . ' ' . $time_out . ':00';
            $updateStmt->execute([$startDatetime, $endDatetime, $employee_id, $date]);
            $updateAttendance->execute([$startDatetime, $endDatetime, $employee_id, $date]);
        }
        $success = "Schedule updated successfully!";
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
            $error = "Schedule already exist for: " . implode(', ', $duplicateDates) . ".";
        } else {
            $insertSchedule = $pdo->prepare("
                INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day)
                VALUES (?, ?, ?, ?, 0)
            ");
            $insertAttendance = $pdo->prepare("
                INSERT INTO attendances (
                    employee_id, schedule_id, work_date,
                    scheduled_start, scheduled_end,
                    actual_time_in, actual_time_out,
                    total_work_minutes, late_minutes,
                    undertime_minutes, overtime_minutes,
                    status, missed_time_out
                )
                VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, 0, 0, 0, 'incomplete', 0)
                ON DUPLICATE KEY UPDATE
                    scheduled_start = VALUES(scheduled_start),
                    scheduled_end   = VALUES(scheduled_end)
            ");

            foreach ($dates as $date) {
                $startDatetime = $date . ' ' . $time_in . ':00';
                $endDatetime   = $is_overnight
                    ? date('Y-m-d', strtotime($date . ' +1 day')) . ' ' . $time_out . ':00'
                    : $date . ' ' . $time_out . ':00';

                $insertSchedule->execute([$employee_id, $date, $startDatetime, $endDatetime]);
                $scheduleId = $pdo->lastInsertId() ?: null;

                $insertAttendance->execute([
                    $employee_id,
                    $scheduleId,
                    $date,
                    $startDatetime,
                    $endDatetime,
                ]);
            }
            $success = "Schedule saved successfully!";
        }
    }
}

// ---- GET ALL SCHEDULES ----
$schedules = $pdo->query("
    SELECT
        e.name AS employee_name,
        s.employee_id,
        s.schedule_date,
        s.scheduled_start,
        s.scheduled_end,
        s.is_rest_day
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.is_rest_day = 0
    ORDER BY s.schedule_date DESC, e.name
")->fetchAll(PDO::FETCH_ASSOC);

// ---- GET ALL EMPLOYEES ----
$employees = $pdo->query("SELECT id, name FROM employees WHERE role = 'employee' ORDER BY name")
                 ->fetchAll(PDO::FETCH_ASSOC);

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

    <style>
        body::before { background-image: url('../images/drt_bg.jpg'); }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    <?php include '../topbar.php'; ?>

    <!-- PAGE WRAPPER -->
    <div class="scheduleWrapper">
        <div class="scheduleBox">

            <!-- TITLE ROW -->
            <div class="adminTitleRow">
                <h5 class="adminTitle">Schedule Management</h5>
                <input type="text" id="searchInput" class="searchInput" placeholder="Search schedule..." onkeyup="searchTable()">
            </div>

            <!-- ALERTS -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- SCHEDULE TABLE -->
            <div class="tableScrollWrapper">
                <table class="table table-bordered table-hover mt-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($schedules) > 0): ?>
                            <?php foreach ($schedules as $row): ?>
                                <?php
                                    $startDT     = $row['scheduled_start'];
                                    $endDT       = $row['scheduled_end'];
                                    $isOvernight = $startDT && $endDT &&
                                                   date('Y-m-d', strtotime($endDT)) > $row['schedule_date'];
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['schedule_date'])) ?></td>
                                    <td><?= $startDT ? date('h:i A', strtotime($startDT)) : '—' ?></td>
                                    <td><?= $endDT   ? date('h:i A', strtotime($endDT))   : '—' ?></td>
                                    <td>
                                        <?php if ($isOvernight): ?>
                                            <span class="badge nightShiftBadge">Night Shift</span>
                                        <?php else: ?>
                                            <span class="badge dayShiftBadge">Day Shift</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actionDropdownWrapper">
                                            <button class="btn btn-sm actionToggle" onclick="toggleActionMenu(this)">
                                                Actions <i class="bi bi-chevron-down"></i>
                                            </button>
                                            <div class="actionMenu">
                                                <a class="actionItem" style="cursor:pointer;" onclick="loadEdit(
                                                    '<?= $row['employee_id'] ?>',
                                                    '<?= htmlspecialchars($row['employee_name'], ENT_QUOTES) ?>',
                                                    '<?= $row['schedule_date'] ?>',
                                                    '<?= $startDT ? date('H:i', strtotime($startDT)) : '' ?>',
                                                    '<?= $endDT   ? date('H:i', strtotime($endDT))   : '' ?>'
                                                )">
                                                    <i class="bi bi-pencil-fill"></i> Edit
                                                </a>
                                                <a href="admin_schedule.php?delete=<?= $row['employee_id'] ?>&date=<?= $row['schedule_date'] ?>"
                                                    class="actionItem deleteItem"
                                                    onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                    <i class="bi bi-trash-fill"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center">No schedules found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ADD / EDIT FORM -->
            <div class="adminFormWrapper">
                <h6 class="formTitle" id="formTitle">Add New Schedule</h6>
                <form method="POST" action="admin_schedule.php" onsubmit="return prepareSubmit()">

                    <input type="hidden" name="employee_id" id="employeeSelect">
                    <input type="hidden" name="selected_dates" id="selectedDatesInput">
                    <input type="hidden" name="is_edit" id="isEditMode" value="0">

                    <div class="formGrid">

                        <!-- Employee Search -->
                        <div class="formGroup" style="position:relative;">
                            <label>Employee</label>
                            <input type="text" id="employeeSearch" class="formControl"
                                placeholder="Type to search employee..." autocomplete="off"
                                oninput="filterEmployees()">
                            <div id="employeeDropdown" class="employeeDropdown">
                                <?php foreach ($employees as $emp): ?>
                                    <div class="employeeOption"
                                        data-id="<?= $emp['id'] ?>"
                                        data-name="<?= htmlspecialchars($emp['name']) ?>"
                                        onclick="selectEmployee(this)">
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </div>
                                <?php endforeach; ?>
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

                    <!-- Form Actions -->
                    <div class="formActions">
                        <button type="submit" class="btnSave">
                            <i class="bi bi-check-circle-fill"></i>
                            <span id="submitLabel">Save Schedule</span>
                        </button>
                        <a href="admin_schedule.php" class="btnCancel" id="cancelBtn" style="display:none;">Cancel</a>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        // ---- FLATPICKR MULTI-SELECT DATE PICKER ----
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

        // ---- UPDATE SELECTED DATES TAGS ----
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

        // ---- REMOVE A DATE TAG ----
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
            if (!document.getElementById('timeIn').value) {
                alert('Please enter a time in.');
                return false;
            }
            if (!document.getElementById('timeOut').value) {
                alert('Please enter a time out.');
                return false;
            }
            document.getElementById('selectedDatesInput').value = JSON.stringify(selectedDates);
            return true;
        }

        // ---- LOAD EDIT INTO FORM ----
        function loadEdit(employeeId, employeeName, date, timeIn, timeOut) {
            document.getElementById('employeeSearch').value      = employeeName;
            document.getElementById('employeeSelect').value      = employeeId;
            document.getElementById('timeIn').value              = timeIn;
            document.getElementById('timeOut').value             = timeOut;
            document.getElementById('formTitle').textContent     = 'Edit Schedule';
            document.getElementById('submitLabel').textContent   = 'Update Schedule';
            document.getElementById('cancelBtn').style.display   = 'inline-block';
            document.getElementById('isEditMode').value          = '1';

            selectedDates = [date];
            fp.setDate(selectedDates);
            updateSelectedDatesList();

            document.querySelector('.adminFormWrapper').scrollIntoView({ behavior: 'smooth' });
        }

        // ---- ACTION DROPDOWN ----
        function toggleActionMenu(btn) {
            const menu = btn.nextElementSibling;
            document.querySelectorAll('.actionMenu').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu.classList.toggle('show');
        }

        // ---- SEARCH TABLE ----
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.tableScrollWrapper tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
            });
        }

        // ---- EMPLOYEE SEARCH FILTER ----
        function filterEmployees() {
            const input    = document.getElementById('employeeSearch').value.toLowerCase();
            const dropdown = document.getElementById('employeeDropdown');
            const options  = document.querySelectorAll('.employeeOption');

            dropdown.style.display = input === '' ? 'none' : 'block';
            options.forEach(opt => {
                opt.style.display = opt.getAttribute('data-name').toLowerCase().includes(input) ? 'block' : 'none';
            });
            document.getElementById('employeeSelect').value = '';
        }

        function selectEmployee(el) {
            document.getElementById('employeeSearch').value        = el.getAttribute('data-name');
            document.getElementById('employeeSelect').value        = el.getAttribute('data-id');
            document.getElementById('employeeDropdown').style.display = 'none';
        }

        // ---- CLOSE DROPDOWNS ON OUTSIDE CLICK ----
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.actionDropdownWrapper')) {
                document.querySelectorAll('.actionMenu').forEach(m => m.classList.remove('show'));
            }
            if (!e.target.closest('#employeeSearch') && !e.target.closest('#employeeDropdown')) {
                document.getElementById('employeeDropdown').style.display = 'none';
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