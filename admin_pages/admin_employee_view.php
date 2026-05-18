<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
require_once '../send_mail.php';
require_once '../system_functions/system_service.php';
require_once '../system_functions/system_library.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_GET['employee_id'])) {
    header("Location: admin_manage_employees.php");
    exit();
}

$refStmt = $pdo->prepare("SELECT id, employee_id FROM employees WHERE employee_id = ? LIMIT 1");
$refStmt->execute([trim($_GET['employee_id'])]);
$empLookup = $refStmt->fetch(PDO::FETCH_ASSOC);
if (!$empLookup) {
    header("Location: admin_manage_employees.php");
    exit();
}
$employeeId     = (int) $empLookup['id'];
$urlEmpId   = $empLookup['employee_id'];
$scheduleStatus = 'approved';

// ---- HANDLE EMPLOYEE EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_employee') {

    $fullName      = trim($_POST['name'] ?? '');
    $nameParts     = preg_split('/\s+/', $fullName, 2);
    $firstName     = $nameParts[0] ?? '';
    $lastName      = $nameParts[1] ?? '';
    $resetPassword = isset($_POST['reset_password']);
    $email         = trim($_POST['email'] ?? '');
    $roleKey       = $_POST['role'] ?? 'employee';
    $department    = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $empRefId      = trim($_POST['employee_ref_id'] ?? '');

    if ($empRefId !== '' && !preg_match('/^\d{6}$/', $empRefId)) {
        header("Location: admin_employee_view.php?employee_id=$urlEmpId&edit_error=invalid_emp_id");
        exit();
    }

    if ($empRefId !== '') {
        $dupIdStmt = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ? AND id != ? LIMIT 1");
        $dupIdStmt->execute([$empRefId, $employeeId]);
        if ($dupIdStmt->fetch()) {
            header("Location: admin_employee_view.php?employee_id=$urlEmpId&edit_error=duplicate_emp_id");
            exit();
        }
    }

    $roleRow = $pdo->prepare("SELECT id FROM roles WHERE role_key = ?");
    $roleRow->execute([$roleKey]);
    $roleId  = $roleRow->fetchColumn() ?: null;

    $dup = $pdo->prepare("
        SELECT id
        FROM employees
        WHERE email = ?
        AND id != ?
        LIMIT 1
    ");

    $dup->execute([$email, $employeeId]);

    if ($dup->fetch()) {
        header("Location: admin_employee_view.php?employee_id=$urlEmpId&edit_error=duplicate_email");
        exit();
    }

    // Fetch old email before updating so we can notify it if it changes
    $oldStmt = $pdo->prepare("SELECT email FROM employees WHERE id = ?");
    $oldStmt->execute([$employeeId]);
    $oldEmail = $oldStmt->fetchColumn() ?: null;

    $pdo->prepare("
        UPDATE employees
        SET
            employee_id = ?,
            first_name = ?,
            last_name = ?,
            email = ?,
            role_id = ?,
            department_id = ?
        WHERE id = ?
    ")->execute([
        $empRefId !== '' ? $empRefId : null,
        $firstName,
        $lastName,
        $email,
        $roleId,
        $department,
        $employeeId
    ]);

    $notifyName  = htmlspecialchars($firstName . ' ' . $lastName);
    $emailChanged = $oldEmail && strtolower($oldEmail) !== strtolower($email);

    // Notify old email if the email address was changed
    if ($emailChanged) {
        sendMail($oldEmail, $notifyName, 'Your HSN DTR Account Email Has Been Updated', "
            <p>Hi {$notifyName},</p>
            <p>This is a notification that the email address for your HSN DTR System account has been changed.</p>
            <p><strong>Old Email:</strong> {$oldEmail}<br>
               <strong>New Email:</strong> {$email}</p>
            <p>If you did not request this change, please contact your administrator immediately.</p>
            <p>— HSN DTR System</p>
        ");
    }

    // Notify current email if the password was changed
    if ($resetPassword) {

        $defaultPassword = 'HSN.123';

        $pdo->prepare("
            UPDATE employees
            SET password = ?
            WHERE id = ?
        ")->execute([
            $defaultPassword,
            $employeeId
        ]);

        sendMail(
            $email,
            $notifyName,
            'Your Password Has Been Reset',
            "
            <p>Hi {$notifyName},</p>
            <p>Your password has been reset by an administrator.</p>

            <p><strong>New Password:</strong> {$defaultPassword}</p>

            <p>Please change it after login.</p>

            <p>— HSN DTR System</p>
            "
        );
    }

    header("Location: admin_employee_view.php?employee_id=$urlEmpId");
    exit();
}

// ---- HANDLE AJAX DELETE (schedule) ----
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

// ---- HANDLE ADD / EDIT SCHEDULE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'save_schedule'    ) {
    $postEmpId    = intval($_POST['employee_id'] ?? 0);
    $dates        = json_decode($_POST['selected_dates'] ?? '[]', true);
    $time_in      = $_POST['time_in']  ?? '';
    $time_out     = $_POST['time_out'] ?? '';
    $is_edit      = !empty($_POST['is_edit']) && $_POST['is_edit'] === '1';
    $is_rest_day  = ($_POST['is_rest_day'] ?? '0') === '1';
    $is_overnight = $time_out < $time_in;

    if (!empty($dates) && $postEmpId) {
        $existsStmt = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND schedule_date = ?");

        if ($is_rest_day) {
            $updRest = $pdo->prepare("UPDATE schedules SET is_rest_day = 1, scheduled_start = NULL, scheduled_end = NULL, status = ? WHERE employee_id = ? AND schedule_date = ?");
            $insRest = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day, status, requested_by) VALUES (?, ?, NULL, NULL, 1, ?, ?)");
            foreach ($dates as $date) {
                $existsStmt->execute([$postEmpId, $date]);
                if ($existsStmt->fetch()) {
                    $updRest->execute([$scheduleStatus, $postEmpId, $date]);
                } else {
                    $insRest->execute([$postEmpId, $date, $scheduleStatus, $_SESSION['user_id']]);
                }
            }
        } else {
            $updateStmt       = $pdo->prepare("UPDATE schedules SET scheduled_start = ?, scheduled_end = ?, is_rest_day = 0, status = ? WHERE employee_id = ? AND schedule_date = ?");
            $updateAttendance = $pdo->prepare("UPDATE attendances SET scheduled_start = ?, scheduled_end = ? WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL");
            $insertSchedule   = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day, status, requested_by) VALUES (?, ?, ?, ?, 0, ?, ?)");
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

                $existsStmt->execute([$postEmpId, $date]);
                if ($existsStmt->fetch()) {
                    $updateStmt->execute([$startDT, $endDT, $scheduleStatus, $postEmpId, $date]);
                    $updateAttendance->execute([$startDT, $endDT, $postEmpId, $date]);
                } else {
                    $insertSchedule->execute([$postEmpId, $date, $startDT, $endDT, $scheduleStatus, $_SESSION['user_id']]);
            $schedId = $pdo->lastInsertId() ?: null;
                        $insertAttendance->execute([$postEmpId, $schedId, $date, $startDT, $endDT]);
                }
            }
        }
    }

    header("Location: admin_employee_view.php?employee_id=$urlEmpId");
    exit();
}
// ---- HANDLE SAVE COMBINED (schedule dates + rest days in one submit) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_combined') {
    $postEmpId     = intval($_POST['employee_id'] ?? 0);
    $dates         = json_decode($_POST['selected_dates'] ?? '[]', true);
    $time_in       = $_POST['time_in']  ?? '';
    $time_out      = $_POST['time_out'] ?? '';
    $restDays      = json_decode($_POST['rest_days'] ?? '[]', true);
    $restDaysDirty = ($_POST['rest_days_dirty'] ?? '0') === '1';
    $is_overnight  = $time_out < $time_in;

    if (!empty($dates) && $postEmpId && $time_in && $time_out) {
        $existsStmt       = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND schedule_date = ?");
        $updateStmt       = $pdo->prepare("UPDATE schedules SET scheduled_start = ?, scheduled_end = ?, is_rest_day = 0, status = ? WHERE employee_id = ? AND schedule_date = ?");
        $updateAttendance = $pdo->prepare("UPDATE attendances SET scheduled_start = ?, scheduled_end = ? WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL");
        $insertSchedule   = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day, status, requested_by) VALUES (?, ?, ?, ?, 0, ?, ?)");
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

            $existsStmt->execute([$postEmpId, $date]);
            if ($existsStmt->fetch()) {
                $updateStmt->execute([$startDT, $endDT, $scheduleStatus, $postEmpId, $date]);
                $updateAttendance->execute([$startDT, $endDT, $postEmpId, $date]);
            } else {
                $insertSchedule->execute([$postEmpId, $date, $startDT, $endDT, $scheduleStatus, $_SESSION['user_id']]);
            $schedId = $pdo->lastInsertId() ?: null;
                    $insertAttendance->execute([$postEmpId, $schedId, $date, $startDT, $endDT]);
            }
        }
    }

    // ---- Single specific rest dates ----
    $singleRestDates = json_decode($_POST['single_rest_dates'] ?? '[]', true);
    if (!empty($singleRestDates) && $postEmpId && is_array($singleRestDates)) {
        $chkStmt = $pdo->prepare("SELECT id FROM schedules WHERE employee_id = ? AND schedule_date = ?");
        $insRest = $pdo->prepare("INSERT INTO schedules (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day, status, requested_by) VALUES (?, ?, NULL, NULL, 1, ?, ?)");
        $updRest = $pdo->prepare("UPDATE schedules SET is_rest_day = 1, scheduled_start = NULL, scheduled_end = NULL, status = ? WHERE employee_id = ? AND schedule_date = ?");
        $delAtt  = $pdo->prepare("DELETE FROM attendances WHERE employee_id = ? AND work_date = ? AND actual_time_in IS NULL");
        foreach ($singleRestDates as $date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            $chkStmt->execute([$postEmpId, $date]);
            if ($chkStmt->fetch()) {
                $updRest->execute([$scheduleStatus, $postEmpId, $date]);
            } else {
                $insRest->execute([$postEmpId, $date, $scheduleStatus, $_SESSION['user_id']]);
            }
            $delAtt->execute([$postEmpId, $date]);
        }
    }

    if ($postEmpId && $restDaysDirty && is_array($restDays)) {
        $pdo->prepare("UPDATE schedules SET is_rest_day = 0 WHERE employee_id = ? AND is_rest_day = 1")
            ->execute([$postEmpId]);

        if (!empty($restDays)) {
            $monthsStmt = $pdo->prepare("
                SELECT DISTINCT DATE_FORMAT(schedule_date, '%Y-%m') AS ym
                FROM schedules
                WHERE employee_id = ?
                ORDER BY ym
            ");
            $monthsStmt->execute([$postEmpId]);
            $months = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

            $setRestStmt = $pdo->prepare("
                UPDATE schedules
                SET is_rest_day = 1
                WHERE employee_id = ? AND schedule_date = ?
            ");

            foreach ($months as $ym) {
                [$y, $m]     = explode('-', $ym);
                $firstDay    = sprintf('%04d-%02d-01', (int)$y, (int)$m);
                $daysInMonth = (int) date('t', strtotime($firstDay));

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dateStr = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, $d);
                    $dow     = (int) date('w', strtotime($dateStr));
                    if (in_array($dow, $restDays)) {
                        $setRestStmt->execute([$postEmpId, $dateStr]);
                    }
                }
            }
        }
    }

    header("Location: admin_employee_view.php?employee_id=$urlEmpId");
    exit();
}
// ---- HANDLE SAVE REST DAY ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_rest_day') {
    $postEmpId = intval($_POST['employee_id'] ?? 0);
    $restDays  = json_decode($_POST['rest_days'] ?? '[]', true);

    if ($postEmpId && is_array($restDays)) {

        $pdo->prepare("UPDATE schedules SET is_rest_day = 0 WHERE employee_id = ? AND is_rest_day = 1")
            ->execute([$postEmpId]);

        if (!empty($restDays)) {
            $monthsStmt = $pdo->prepare("
                SELECT DISTINCT DATE_FORMAT(schedule_date, '%Y-%m') AS ym
                FROM schedules
                WHERE employee_id = ?
                ORDER BY ym
            ");
            $monthsStmt->execute([$postEmpId]);
            $months = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

            $setRestStmt = $pdo->prepare("
                UPDATE schedules
                SET is_rest_day = 1
                WHERE employee_id = ? AND schedule_date = ?
            ");

            foreach ($months as $ym) {
                [$y, $m]     = explode('-', $ym);
                $firstDay    = sprintf('%04d-%02d-01', (int)$y, (int)$m);
                $daysInMonth = (int) date('t', strtotime($firstDay));

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dateStr = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, $d);
                    $dow     = (int) date('w', strtotime($dateStr));
                    if (in_array($dow, $restDays)) {
                        $setRestStmt->execute([$postEmpId, $dateStr]);
                    }
                }
            }
        }
    }

    header("Location: admin_employee_view.php?employee_id=$urlEmpId");
    exit();
}

// ---- HANDLE EMPLOYEE DELETE ----
if (isset($_GET['action']) && $_GET['action'] === 'delete_employee') {

    $pdo->prepare("DELETE ler FROM log_edit_requests ler INNER JOIN logs l ON ler.log_id = l.id WHERE l.employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM logs WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM attendances WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM schedules WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$employeeId]);

    header("Location: admin_manage_employees.php");
    exit();
}


// ---- GET EMPLOYEE ----
$stmt = $pdo->prepare("
    SELECT e.*,
           CONCAT(e.first_name, ' ', e.last_name) AS name,
           r.role_key  AS role,
           r.role_name,
           d.department_name,
           d.department_code
    FROM employees e
    LEFT JOIN roles r ON r.id = e.role_id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

// ---- GET ALL ROLES (for edit form) ----
$roles = $pdo->query("SELECT role_key, role_name FROM roles ORDER BY id")
             ->fetchAll(PDO::FETCH_ASSOC);

if (!$emp) {
    header("Location: admin_employees_list.php");
    exit();
}

// ---- VIEW MONTH ----
$rawMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
    $rawMonth = date('Y-m');
}
[$viewYear, $viewMonthNum] = array_map('intval', explode('-', $rawMonth));
$monthStart = sprintf('%04d-%02d-01', $viewYear, $viewMonthNum);
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$prevMonth  = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth  = date('Y-m', strtotime($monthStart . ' +1 month'));
$monthLabel = date('F Y', strtotime($monthStart));
$todayStr   = date('Y-m-d');


// ---- RECORDS (gantt) ----
$records       = getAttendanceRecords($pdo, $employeeId, $monthStart, $monthEnd);
$schedForGantt = getSchedulesByDateRange($pdo, $employeeId, $monthStart, $monthEnd);

// ---- LOGS ----
$logsStmt = $pdo->prepare("
    SELECT log_type, log_time, is_within_office, distance_meters
    FROM logs
    WHERE employee_id = ? AND DATE(log_time) BETWEEN ? AND ?
    ORDER BY log_time DESC
");
$logsStmt->execute([$employeeId, $monthStart, $monthEnd]);
$tapLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- LEAVE BALANCE ----
$balStmt = $pdo->prepare("SELECT * FROM employee_leave_balances WHERE employee_id = ?");
$balStmt->execute([$employeeId]);
$leaveBalance = $balStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$leaveTypes = [
    ['key' => 'vacation_leave',    'label' => 'Vacation Leave',    'icon' => 'bi-umbrella-fill',    'color' => '#4da3ff',              'default' => 0],
    ['key' => 'sick_leave',        'label' => 'Sick Leave',        'icon' => 'bi-heart-pulse-fill', 'color' => '#ff6b7a',              'default' => 4],
    ['key' => 'birthday_leave',    'label' => 'Birthday Leave',    'icon' => 'bi-gift-fill',        'color' => '#f0ad4e',              'default' => 1],
    ['key' => 'paternity_leave',   'label' => 'Paternity Leave',   'icon' => 'bi-person-fill',      'color' => '#7dd9a8',              'default' => 7],
    ['key' => 'maternity_leave',   'label' => 'Maternity Leave',   'icon' => 'bi-person-hearts',    'color' => '#fd7e14',              'default' => 90],
    ['key' => 'solo_parent_leave', 'label' => 'Solo Parent Leave', 'icon' => 'bi-people-fill',      'color' => '#a07de0',              'default' => 1],
    ['key' => 'buffer_leave',      'label' => 'Buffer Leave',      'icon' => 'bi-shield-fill',      'color' => 'var(--primary-color)', 'default' => 0],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($emp['name']) ?> — Employee View</title>

    <!-- Bootstrap + Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- Page component CSS (calendar + gantt styles) -->
    <link rel="stylesheet" href="admin_employee_view.css">
    <link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">
    <link rel="stylesheet" href="../employee_pages/logs_widget.css">
    <link rel="stylesheet" href="../employee_pages/records_widget.css">
    <link rel="stylesheet" href="../employee_pages/schedules_widget.css">

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php $currentPage = 'employees'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; $employeeId = $emp['id']; ?>

    <!-- Employee header strip -->
    <div class="card card-neutral employee-view-card">
        <div class="card-body employee-view-card-body">
            <div class="ev-header-row">

                <!-- Back button -->
                <a href="admin_manage_employees.php" class="btn btn-sm btn-outline-light ev-back-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>

                <!-- Avatar + info -->
                <div class="ev-emp-info">
                  <div class="d-flex align-items-center gap-2">
                        <?php
                        $avatar = !empty($emp['profile_image'])
                            ? $emp['profile_image']
                            : 'default_profile.png';
                        ?>
                        <img src="../assets/user_profiles/<?= htmlspecialchars($avatar) ?>"
                            class="ev-avatar" alt="avatar">
                    </div>
                    <div class="ev-emp-details">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="ev-emp-name"><?= htmlspecialchars($emp['name']) ?></span>
                            <span class="empRoleBadge empRole-<?= htmlspecialchars($emp['role']) ?>">
                                <?= ucfirst($emp['role']) ?>
                            </span>
                        </div>
                        <div class="ev-emp-metas">
                            <span class="ev-emp-meta">
                                <i class="bi bi-person-badge"></i>
                                ID: <?= htmlspecialchars($emp['employee_id'] ?? '—') ?>
                            </span>
                            <?php if (!empty($emp['department_name'])): ?>
                                <span class="ev-emp-meta">
                                    <i class="bi bi-diagram-3"></i>
                                    <?= htmlspecialchars($emp['department_name']) ?>
                                </span>
                            <?php endif; ?>
                            <span class="ev-emp-meta">
                                <i class="bi bi-envelope"></i>
                                <?= htmlspecialchars($emp['email']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <!-- Actions -->
                <div class="ev-actions">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#edit-employee-modal" class="btn btn-info">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php
    $editError = $_GET['edit_error'] ?? '';
    $editErrorMessages = [
        'duplicate_email'  => 'That email is already in use by another employee.',
        'duplicate_emp_id' => 'That Employee ID is already in use by another employee.',
        'invalid_emp_id'   => 'Employee ID must be exactly 6 digits.',
    ];
    if (isset($editErrorMessages[$editError])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        showToast(<?= json_encode($editErrorMessages[$editError]) ?>, 'danger');
    });
    </script>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="ev-tabs card-neutral">
        <div class="card-header p-0">
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">

                <li class="nav-item" role="presentation">
                    <button class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab1">
                        <i class="bi bi-calendar3"></i> Schedules
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab2">
                        <i class="bi bi-file-earmark-text"></i> Records
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab3">
                        <i class="bi bi-clock-history"></i> Logs
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab4">
                        <i class="bi bi-calendar-heart"></i> Leave Balance
                    </button>
                </li>

            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">

                <div class="tab-pane fade show active" id="tab1" role="tabpanel">

                    <?php
                    $schedEmployeeId   = $employeeId;
                    $schedEmpUrlId     = $urlEmpId;
                    $schedCalApiPath   = 'get_admin_employee_calendar.php';
                    $schedSaveApiPath  = 'admin_employee_view.php?employee_id=' . htmlspecialchars($urlEmpId);
                    $schedCurrentMonth = $rawMonth;
                    $schedInitialDate  = $monthStart;
                    include '../employee_pages/schedules_widget.php';
                    ?>

                </div>

                <div class="tab-pane fade" id="tab2" role="tabpanel">

                    <?php 
                    $recordsEmployeeId = $employeeId;
                    $startDate      = $monthStart;
                    $endDate        = $monthEnd;
                    $recordsApiPath = '../get_records.php';
                    include '../employee_pages/records_widget.php'; ?>

                </div>

                <div class="tab-pane fade" id="tab3" role="tabpanel">

                    <?php
                    $logsEmployeeId = $employeeId;
                    $startDate      = $monthStart;
                    $endDate        = $monthEnd;
                    $logsApiPath    = '../get_logs.php';
                    include '../employee_pages/logs_widget.php';
                    ?>

                </div>

                <!-- Tab 4: Leave Balance -->
                <div class="tab-pane fade" id="tab4" role="tabpanel">
                    <?php if (empty($leaveBalance)): ?>
                        <div class="sched-cal-empty">
                            <div class="sched-cal-empty-icon"><i class="bi bi-exclamation-circle"></i></div>
                            No leave balance record found for this employee.
                        </div>
                    <?php else: ?>
                        <div class="leave-cards-grid">
                            <?php foreach ($leaveTypes as $lt): ?>
                                <div class="leave-card"
                                     data-key="<?= $lt['key'] ?>"
                                     data-emp="<?= $employeeId ?>"
                                     data-default="<?= $lt['default'] ?>">
                                    <div class="leave-card-actions">
                                        <button class="leave-card-btn leave-reset-btn" title="Reset to default (<?= $lt['default'] ?>)">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                        <span class="leave-card-btn leave-edit-icon">
                                            <i class="bi bi-pencil"></i>
                                        </span>
                                    </div>
                                    <div class="leave-card-icon" style="color:<?= $lt['color'] ?>">
                                        <i class="bi <?= $lt['icon'] ?>"></i>
                                    </div>
                                    <div class="leave-card-days"
                                         style="color:<?= $lt['color'] ?>"
                                         data-value="<?= (int)($leaveBalance[$lt['key']] ?? 0) ?>">
                                        <?= (int)($leaveBalance[$lt['key']] ?? 0) ?>
                                    </div>
                                    <div class="leave-card-name"><?= $lt['label'] ?></div>
                                    <div class="leave-card-unit">days available</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

</div><!-- #main-wrapper -->



<!-- Edit Employee Modal -->
<div class="modal fade" id="edit-employee-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Editing <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="admin_employee_view.php?employee_id=<?= htmlspecialchars($urlEmpId) ?>">

                <input type="hidden" name="action" value="edit_employee">

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Employee ID</label>
                            <input type="text" name="employee_ref_id" id="editEmpRefId" class="form-control"
                                   value="<?= htmlspecialchars($emp['employee_id'] ?? '') ?>"
                                   maxlength="6" placeholder="6-digit number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($emp['email']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="reset_password"
                                    id="reset_password">

                                <label class="form-check-label" for="reset_password">
                                    Reset Password
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>

                            <div class="dropdown w-100">
                                <button class="btn btn-outline-light dropdown-toggle w-100 text-start" type="button" id="roleDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span id="roleLabel"><?= htmlspecialchars($emp['role_name']) ?></span>
                                </button>

                                <ul class="dropdown-menu w-100" id="roleDropdown">
                                    <?php foreach ($roles as $r): ?>
                                    <li>
                                        <button class="dropdown-item" type="button" onclick="selectRole('<?= $r['role_key'] ?>', '<?= $r['role_name'] ?>')">
                                            <?= $r['role_name'] ?>
                                        </button>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <input type="hidden"
                                name="role"
                                id="roleInput"
                                value="<?= htmlspecialchars($emp['role']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <div class="dropdown w-100">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="edit-dept-search" class="form-control"
                                           value="<?= htmlspecialchars($emp['department_name'] ?? '') ?>"
                                           placeholder="Select Department" autocomplete="off">
                                </div>
                                <ul class="dropdown-menu p-2 w-100" id="edit-dept-menu"></ul>
                            </div>
                            <input type="hidden" name="department_id" id="edit-dept-id"
                                   value="<?= htmlspecialchars($emp['department_id'] ?? '') ?>">
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle-fill"></i> Update Employee
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Delete Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                Are you sure you want to delete <strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>?
                This will permanently remove their schedules and logs.
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="admin_employee_view.php?employee_id=<?= htmlspecialchars($urlEmpId) ?>&action=delete_employee" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Delete
                </a>
            </div>

        </div>
    </div>
</div>

<!-- ===== EDIT LOG MODAL ===== -->
<div class="modal fade" id="editLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Edit Log Entry
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Log Type</label>
                    <input type="text" class="form-control" id="editLogTypeDisplay" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Date &amp; Time</label>
                    <input type="datetime-local" class="form-control" id="editLogTime" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">
                        Reason <small class="text-muted">(optional)</small>
                    </label>
                    <textarea class="form-control" id="editLogReason" rows="2"
                        placeholder="e.g. System error, forgot to clock in..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="editLogSubmitBtn">
                    <i class="bi bi-check-circle-fill"></i> Update Log
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ===== ADMIN LOG EDIT MODAL ===== -->
<div class="modal fade" id="adminLogEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Log Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="le-modal-info-row">
                    <span class="le-modal-label">Date</span>
                    <span class="le-modal-value" id="ale-date">—</span>
                </div>
                <div class="le-modal-info-row">
                    <span class="le-modal-label">Log Type</span>
                    <span class="le-modal-value" id="ale-type">—</span>
                </div>
                <div class="le-modal-info-row">
                    <span class="le-modal-label">Current Time</span>
                    <span class="le-modal-value" id="ale-current-time">—</span>
                </div>
                <div class="mt-3">
                    <label class="le-input-label">New Date &amp; Time</label>
                    <input type="datetime-local" id="ale-new-datetime" class="le-time-input" required>
                </div>
                <div class="mt-3">
                    <label class="le-input-label">Reason for Edit</label>
                    <textarea id="ale-reason" class="le-time-input" rows="3"
                        placeholder="Enter reason for edit..."
                        style="resize:none;height:auto;"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ale-submit-btn">
                    <i class="bi bi-check-circle-fill"></i> Apply Edit
                </button>
            </div>

        </div>
    </div>
</div>

<?php include '../toast.php'; ?>

<script src="../system_functions/gantt.js"></script>


<script>
// ---- Department dropdown (Edit Employee modal) ----
(function () {
    fetch('/DTR-Internship-Project/admin_pages/department_api.php?action=list')
        .then(r => r.json())
        .then(depts => {
            const items = [
                { value: '', label: 'None' },
                ...depts.map(d => ({ value: String(d.id), label: d.department_name }))
            ];
            const input  = document.getElementById('edit-dept-search');
            const menu   = document.getElementById('edit-dept-menu');
            const hidden = document.getElementById('edit-dept-id');

            function render(list) {
                menu.innerHTML = '';
                list.forEach(item => {
                    const li  = document.createElement('li');
                    const btn = document.createElement('button');
                    btn.type        = 'button';
                    btn.className   = 'dropdown-item';
                    btn.textContent = item.label;
                    btn.onclick = () => {
                        input.value  = item.label === 'None' ? '' : item.label;
                        hidden.value = item.value;
                        menu.classList.remove('show');
                    };
                    li.appendChild(btn);
                    menu.appendChild(li);
                });
            }

            input.addEventListener('click', () => menu.classList.add('show'));
            input.addEventListener('input', () => {
                const q = input.value.toLowerCase();
                render(items.filter(i => i.label.toLowerCase().includes(q)));
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('#edit-dept-menu') && !e.target.closest('#edit-dept-search'))
                    menu.classList.remove('show');
            });
            render(items);
        })
        .catch(() => {});
})();

// Edit Employee Select Role
function selectRole(value, label) {
    document.getElementById('roleInput').value       = value;
    document.getElementById('roleLabel').textContent = label;
    document.getElementById('roleDropdown').classList.remove('show');
}

// ---- State ----
const EMP_ID     = <?= $employeeId ?>;
const EMP_URL_ID = '<?= htmlspecialchars($urlEmpId) ?>';
let currentMonth = '<?= $rawMonth ?>';
const tabLoadedMonth = { '#tab1': null, '#tab2': null, '#tab3': null };

// ---- Helpers ----
function pad(n) { return String(n).padStart(2, '0'); }
function parseYM(ym) {
    const [y, m] = ym.split('-').map(Number);
    return { year: y, month: m };
}
function monthDates(ym) {
    const { year, month } = parseYM(ym);
    const last = new Date(year, month, 0).getDate();
    return { startDate: `${year}-${pad(month)}-01`, endDate: `${year}-${pad(month)}-${pad(last)}` };
}
function monthLabel(ym) {
    const { year, month } = parseYM(ym);
    return new Date(year, month - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}


// ---- Load tab by target ----
function loadTab(tabTarget, ym) {
    const { startDate, endDate } = monthDates(ym);
    if      (tabTarget === '#tab1') { if (typeof window.swUpdateSize === 'function') window.swUpdateSize(); }
    else if (tabTarget === '#tab2') loadRecords(startDate, endDate);
    else if (tabTarget === '#tab3') loadLogs(startDate, endDate);
}


// ---- Tab 2: Records / Gantt ----
function loadRecords(start, end) {
    const ym = start ? start.substring(0, 7) : currentMonth;
    const monthEl = document.getElementById('current-month');
    if (monthEl) monthEl.value = ym;
    if (typeof fetchRecords === 'function') fetchRecords(ym);
}

// ---- Tab 3: bridge to logs widget ----
function loadLogs(start, end) {
    if (start) document.getElementById('startDate').value = start;
    if (end)   document.getElementById('endDate').value   = end;
    fetchLogs();
}


function padEmpId(input) {
    const v = input.value.trim();
    if (v !== '') input.value = v.padStart(6, '0');
}

// ---- DOMContentLoaded ----
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('editEmpRefId')
        ?.addEventListener('blur', function () { padEmpId(this); });

    const key    = 'empViewTab_' + EMP_ID;
    const stored = localStorage.getItem(key);
    if (stored) {
        const tabEl = document.querySelector(`[data-bs-target="${stored}"]`);
        if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
    }

    document.querySelectorAll('#myTab [data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => {
            const target = e.target.dataset.bsTarget;
            localStorage.setItem(key, target);
            if (tabLoadedMonth[target] !== currentMonth) {
                loadTab(target, currentMonth);
                tabLoadedMonth[target] = currentMonth;
            }
        });
    });

    // ---- Tab 1: widget auto-initializes, mark it as loaded ----
    tabLoadedMonth['#tab1'] = currentMonth;

    const activeBtn = document.querySelector('#myTab .nav-link.active');
    const activeTab = activeBtn ? activeBtn.dataset.bsTarget : '#tab1';
    if (activeTab !== '#tab1') {
        loadTab(activeTab, currentMonth);
        tabLoadedMonth[activeTab] = currentMonth;
    }

    // ---- Schedule widget month changes → sync other tabs ----
    document.addEventListener('scheduleMonthChanged', e => {
        const ym = e.detail.month;
        currentMonth = ym;
        history.pushState({ month: ym }, '', `?employee_id=${EMP_URL_ID}&month=${ym}`);
        Object.keys(tabLoadedMonth).forEach(k => { tabLoadedMonth[k] = k === '#tab1' ? ym : null; });
        const activeBtn2 = document.querySelector('#myTab .nav-link.active');
        const activeTab2 = activeBtn2 ? activeBtn2.dataset.bsTarget : '#tab1';
        if (activeTab2 !== '#tab1') { loadTab(activeTab2, ym); tabLoadedMonth[activeTab2] = ym; }
    });

    // Browser back / forward
    window.addEventListener('popstate', e => {
        if (e.state && e.state.month) {
            const ym = e.state.month;
            currentMonth = ym;
            if (typeof window.swGotoMonth === 'function') window.swGotoMonth(ym);
            Object.keys(tabLoadedMonth).forEach(k => { tabLoadedMonth[k] = null; });
            tabLoadedMonth['#tab1'] = ym;
            const activeBtn = document.querySelector('#myTab .nav-link.active');
            const activeTab = activeBtn ? activeBtn.dataset.bsTarget : '#tab1';
            if (activeTab !== '#tab1') { loadTab(activeTab, ym); tabLoadedMonth[activeTab] = ym; }
        }
    });

    history.replaceState({ month: currentMonth }, '', `?employee_id=${EMP_URL_ID}&month=${currentMonth}`);

    // ---- Edit Log ----
    let editingLogId   = null;
    let editingLogType = null;

    document.addEventListener('click', e => {
        const btn = e.target.closest('.log-edit-btn');
        if (!btn) return;

        editingLogId   = btn.dataset.logId;
        editingLogType = btn.dataset.logType;

        document.getElementById('editLogTypeDisplay').value =
            editingLogType === 'IN' ? 'Time In' : 'Time Out';
        document.getElementById('editLogTime').value   = btn.dataset.logTime;
        document.getElementById('editLogReason').value = '';

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('editLogModal')
        ).show();
    });

    document.getElementById('editLogSubmitBtn').addEventListener('click', () => {
        const newDatetime = document.getElementById('editLogTime').value;
        const reason      = document.getElementById('editLogReason').value.trim()
                            || 'Edited by admin';

        if (!newDatetime) {
            showToast('Please select a date and time.', 'danger');
            return;
        }

        const body = new FormData();
        body.append('employee_id',  EMP_ID);
        body.append('log_id',       editingLogId);
        body.append('new_datetime', newDatetime.replace('T', ' ') + ':00');
        body.append('reason',       reason);

        fetch('/DTR-Internship-Project/dropdown_requests/request_log_edit.php', {
            method: 'POST',
            body
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(
                    document.getElementById('editLogModal')
                ).hide();
                showToast('Log updated successfully');
                fetchLogs();
            } else {
                showToast(data.message || 'Failed to update log.', 'danger');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'danger'));
    });

});

// ---- Admin Log Edit ----
let currentAdminEditLogId   = null;
let currentAdminEditLogType = null;

function openAdminLogEditModal(btn) {
    currentAdminEditLogId   = btn.dataset.logId;
    currentAdminEditLogType = btn.dataset.logType;
    const typeLabels = { 'IN': 'Time In', 'OUT': 'Time Out', 'BREAK_IN': 'Break In', 'BREAK_OUT': 'Break Out' };
    document.getElementById('ale-date').textContent         = btn.dataset.logDateLabel;
    document.getElementById('ale-type').textContent         = typeLabels[btn.dataset.logType] || btn.dataset.logType;
    document.getElementById('ale-current-time').textContent = btn.dataset.logTimeLabel;
    document.getElementById('ale-new-datetime').value       = btn.dataset.logDatetime;
    document.getElementById('ale-reason').value             = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('adminLogEditModal')).show();
}

document.getElementById('ale-submit-btn').addEventListener('click', () => {
    const newDatetime = document.getElementById('ale-new-datetime').value;
    const reason      = document.getElementById('ale-reason').value.trim();

    if (!newDatetime) {
        showToast('Please enter a new date and time.', 'danger');
        return;
    }

    const btn = document.getElementById('ale-submit-btn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Saving...';

    const form = new FormData();
    form.append('log_id',       currentAdminEditLogId);
    form.append('log_type',     currentAdminEditLogType);
    form.append('employee_id',  EMP_ID);
    form.append('new_datetime', newDatetime);
    form.append('reason',       reason);

    fetch('../dropdown_requests/request_log_edit.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Apply Edit';
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('adminLogEditModal'))?.hide();
                fetchLogs();
                showToast(data.message || 'Log updated successfully.', 'success');
            } else {
                showToast(data.message || 'Failed to update log.', 'danger');
            }
        })
        .catch(() => {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Apply Edit';
            showToast('An error occurred. Please try again.', 'danger');
        });
});

// ---- Leave balance inline edit ----
function saveLeaveBalance(card, val) {
    const daysEl = card.querySelector('.leave-card-days');
    const key    = card.dataset.key;
    const empId  = card.dataset.emp;
    const prev   = parseInt(daysEl.dataset.value, 10);

    const fd = new FormData();
    fd.append('employee_id', empId);
    fd.append('leave_type',  key);
    fd.append('value',       val);

    fetch('update_leave_balance.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            daysEl.textContent   = data.ok ? data.value : prev;
            daysEl.dataset.value = data.ok ? data.value : prev;
            showToast(data.ok ? 'Leave balance updated.' : 'Failed to update leave balance.');
        })
        .catch(() => {
            daysEl.textContent   = prev;
            daysEl.dataset.value = prev;
            showToast('Failed to update leave balance.');
        });
}

document.addEventListener('click', e => {
    // Reset button
    const resetBtn = e.target.closest('.leave-reset-btn');
    if (resetBtn) {
        e.stopPropagation();
        const card   = resetBtn.closest('.leave-card');
        const def    = parseInt(card.dataset.default, 10);
        const daysEl = card.querySelector('.leave-card-days');
        if (daysEl.querySelector('input')) return;
        const cur = parseInt(daysEl.dataset.value, 10);
        if (cur === def) return;
        daysEl.textContent   = def;
        daysEl.dataset.value = def;
        saveLeaveBalance(card, def);
        return;
    }

    // Card click → inline edit
    const card = e.target.closest('.leave-card');
    if (!card) return;
    const daysEl = card.querySelector('.leave-card-days');
    if (!daysEl || daysEl.querySelector('input')) return;

    const color = daysEl.style.color;
    const prev  = parseInt(daysEl.dataset.value, 10);

    const input = document.createElement('input');
    input.type        = 'number';
    input.min         = '0';
    input.value       = prev;
    input.className   = 'leave-days-input';
    input.style.color = color;

    daysEl.textContent = '';
    daysEl.appendChild(input);
    input.focus();
    input.select();

    let saved = false;
    function save() {
        if (saved) return;
        saved = true;
        const val = Math.max(0, parseInt(input.value, 10) || 0);
        daysEl.textContent   = val;
        daysEl.dataset.value = val;
        if (val !== prev) saveLeaveBalance(card, val);
    }

    input.addEventListener('keydown', ev => {
        if (ev.key === 'Enter')  { input.blur(); }
        if (ev.key === 'Escape') { saved = true; daysEl.textContent = prev; daysEl.dataset.value = prev; }
    });
    input.addEventListener('blur', save);
});

</script>

</body>
</html>
