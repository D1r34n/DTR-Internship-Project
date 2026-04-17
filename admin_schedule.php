<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'db.php';
date_default_timezone_set('Asia/Manila');

$success = "";
$error = "";

// HANDLE DELETE
if (isset($_GET['delete']) && isset($_GET['week'])) {
    $employeeId = $_GET['delete'];
    $weekStart = $_GET['week'];
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

    $stmt = $pdo->prepare("DELETE FROM schedules WHERE employee_id = ? AND work_date BETWEEN ? AND ?");
    $stmt->execute([$employeeId, $weekStart, $weekEnd]);

    $success = "Schedule deleted successfully!";
}

// HANDLE ADD / OVERWRITE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $week_monday = $_POST['week_monday'];
    $time_in = $_POST['time_in'];
    $time_out = $_POST['time_out'];

    // Generate all 7 days from Monday
    $days = [];
    $monday = new DateTime($week_monday);

    for ($i = 0; $i < 7; $i++) {
        $current = clone $monday;
        $current->modify("+$i days");
        $dayOfWeek = $current->format('N'); // 1=Mon, 7=Sun
        $isRestDay = ($dayOfWeek >= 6) ? 1 : 0;

        $days[] = [
            'date' => $current->format('Y-m-d'),
            'is_rest_day' => $isRestDay
        ];
    }

    // Delete existing rows for this employee and week
    $weekStart = $monday->format('Y-m-d');
    $weekEnd = (clone $monday)->modify('+6 days')->format('Y-m-d');
    $deleteStmt = $pdo->prepare("DELETE FROM schedules WHERE employee_id = ? AND work_date BETWEEN ? AND ?");
    $deleteStmt->execute([$employee_id, $weekStart, $weekEnd]);

    // Insert new rows
    $insertStmt = $pdo->prepare("INSERT INTO schedules (employee_id, work_date, time_in, time_out, is_rest_day) VALUES (?, ?, ?, ?, ?)");
    foreach ($days as $day) {
        $insertStmt->execute([
            $employee_id,
            $day['date'],
            $day['is_rest_day'] ? null : $time_in,
            $day['is_rest_day'] ? null : $time_out,
            $day['is_rest_day']
        ]);
    }

    $success = "Schedule saved successfully!";
}

// GET ALL SCHEDULES grouped by employee and week
$schedules = $pdo->query("
    SELECT 
        e.name as employee_name,
        s.employee_id,
        MIN(s.work_date) as week_start,
        MAX(s.work_date) as week_end,
        MIN(s.time_in) as time_in,
        MAX(s.time_out) as time_out
    FROM schedules s
    JOIN employees e ON s.employee_id = e.id
    WHERE s.is_rest_day = 0
    AND s.work_date IS NOT NULL
    GROUP BY s.employee_id, YEARWEEK(s.work_date)
    ORDER BY week_start DESC, e.name
")->fetchAll(PDO::FETCH_ASSOC);

// GET ALL EMPLOYEES
$employees = $pdo->query("SELECT id, name FROM employees WHERE role = 'employee' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schedule Management</title>
    <link rel="stylesheet" href="admin_schedule.css">
    <link rel="stylesheet" href="side_and_top_bar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body::before { background-image: url('images/drt_bg.jpg'); }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <?php include 'admin_topbar.php'; ?>

    <div class="adminWrapper">
        <div class="adminBox">

            <div class="adminTitleRow">
                <h5 class="adminTitle">Schedule Management</h5>
                <input type="text" id="searchInput" class="searchInput" placeholder="Search schedule..." onkeyup="searchTable()">
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- SCHEDULE LIST -->
            <div class="tableScrollWrapper">
                <table class="table table-bordered table-hover mt-3">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Week</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($schedules) > 0): ?>
                            <?php foreach ($schedules as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td>
                                        <?= date('M d', strtotime($row['week_start'])) ?> - 
                                        <?= date('M d, Y', strtotime($row['week_end'])) ?>
                                    </td>
                                    <td><?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '—' ?></td>
                                    <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '—' ?></td>
                                    <td>
                                        <div class="actionDropdownWrapper">
                                            <button class="btn btn-sm editBtn actionToggle" onclick="toggleActionMenu(this)">
                                                Actions <i class="bi bi-chevron-down"></i>
                                            </button>
                                            <div class="actionMenu">
                                                <a class="actionItem" style="cursor:pointer;" onclick="loadEdit(
                                                    '<?= $row['employee_id'] ?>',
                                                    '<?= htmlspecialchars($row['employee_name'], ENT_QUOTES) ?>',
                                                    '<?= $row['week_start'] ?>',
                                                    '<?= $row['time_in'] ?>',
                                                    '<?= $row['time_out'] ?>'
                                                )">
                                                    <i class="bi bi-pencil-fill"></i> Edit
                                                </a>
                                                <a href="admin_schedule.php?delete=<?= $row['employee_id'] ?>&week=<?= $row['week_start'] ?>"
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
                            <tr><td colspan="5" class="text-center">No schedules found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ADD / EDIT FORM -->
            <div class="adminFormWrapper">
                <h6 class="formTitle" id="formTitle">Add New Schedule</h6>
                <form method="POST" action="admin_schedule.php">

                    <div class="formGrid">

                        <!-- EMPLOYEE SEARCH INPUT -->
                        <div class="formGroup" style="position:relative;">
                            <label>Employee</label>
                            <input type="text" id="employeeSearch" class="formControl"
                                placeholder="Type to search employee..." autocomplete="off"
                                oninput="filterEmployees()">
                            <input type="hidden" name="employee_id" id="employeeSelect" required>
                            <div id="employeeDropdown" style="
                               display:none;
                               position:absolute;
                               bottom: 60%;
                               top:auto;
                               left:0;
                               background:white;
                               border:1px solid #ccc;
                               border-radius:6px;
                               max-height:180px;
                               overflow-y:auto;
                               z-index:9999;
                               width:100%;
                               box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                               ">
                                <?php foreach ($employees as $emp): ?>
                                    <div class="employeeOption"
                                        style="padding:0.5rem 1rem; cursor:pointer; font-size:0.85rem; font-family:'Poppins',sans-serif;"
                                        onmouseover="this.style.backgroundColor='#f5f5f5'"
                                        onmouseout="this.style.backgroundColor='white'"
                                        data-id="<?= $emp['id'] ?>"
                                        data-name="<?= htmlspecialchars($emp['name']) ?>"
                                        onclick="selectEmployee(this)">
                                        <?= htmlspecialchars($emp['name']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="formGroup">
                            <label>Week Monday Date</label>
                            <input type="date" name="week_monday" id="weekMonday" class="formControl" required>
                        </div>

                        <div class="formGroup">
                            <label>Time In</label>
                            <input type="time" name="time_in" id="timeIn" class="formControl" required>
                        </div>

                        <div class="formGroup">
                            <label>Time Out</label>
                            <input type="time" name="time_out" id="timeOut" class="formControl" required>
                        </div>

                    </div>

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

    <script>
    // Action dropdown toggle
    function toggleActionMenu(btn) {
        const menu = btn.nextElementSibling;
        document.querySelectorAll('.actionMenu').forEach(m => {
            if (m !== menu) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    }

    // Close action menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.actionDropdownWrapper')) {
            document.querySelectorAll('.actionMenu').forEach(m => m.classList.remove('show'));
        }
        // Close employee dropdown when clicking outside
        if (!e.target.closest('#employeeSearch') && !e.target.closest('#employeeDropdown')) {
            document.getElementById('employeeDropdown').style.display = 'none';
        }
    });

    // Load edit into form
    function loadEdit(employeeId, employeeName, weekStart, timeIn, timeOut) {
        document.getElementById('employeeSearch').value = employeeName;
        document.getElementById('employeeSelect').value = employeeId;
        document.getElementById('weekMonday').value = weekStart;
        document.getElementById('timeIn').value = timeIn;
        document.getElementById('timeOut').value = timeOut;
        document.getElementById('formTitle').textContent = 'Edit Schedule';
        document.getElementById('submitLabel').textContent = 'Update Schedule';
        document.getElementById('cancelBtn').style.display = 'inline-block';
        document.querySelector('.adminFormWrapper').scrollIntoView({ behavior: 'smooth' });
    }

    // Search table
    function searchTable() {
        const input = document.getElementById('searchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.tableScrollWrapper tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(input) ? '' : 'none';
        });
    }

    // Auto-dismiss alerts after 3 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 3000);

    // Employee search filter
    function filterEmployees() {
        const input = document.getElementById('employeeSearch').value.toLowerCase();
        const dropdown = document.getElementById('employeeDropdown');
        const options = document.querySelectorAll('.employeeOption');

        dropdown.style.display = input === '' ? 'none' : 'block';

        options.forEach(opt => {
            const name = opt.getAttribute('data-name').toLowerCase();
            opt.style.display = name.includes(input) ? 'block' : 'none';
        });

        // Clear hidden input when typing again
        document.getElementById('employeeSelect').value = '';
    }

    // Select employee from dropdown
    function selectEmployee(el) {
        document.getElementById('employeeSearch').value = el.getAttribute('data-name');
        document.getElementById('employeeSelect').value = el.getAttribute('data-id');
        document.getElementById('employeeDropdown').style.display = 'none';
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>