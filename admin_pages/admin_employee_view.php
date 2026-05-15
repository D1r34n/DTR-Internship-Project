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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- Page component CSS (calendar + gantt styles) -->
    <link rel="stylesheet" href="admin_employee_view.css">
    <link rel="stylesheet" href="../dropdown_requests/log_edit_modal.css">
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

                    <!-- Month nav + Add button -->
                    <div class="tab-section-header">
                        <div class="d-flex align-items-center gap-2">
                            <button class="sched-nav-btn" onclick="navigatePrev()" title="Previous month">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span class="sched-month-label"><?= htmlspecialchars($monthLabel) ?></span>
                            <button class="sched-nav-btn" onclick="navigateNext()" title="Next month">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                            <button class="sched-nav-btn" onclick="navigateToday()" title="Go to today" style="font-size:0.65rem;width:auto;padding:0 8px;letter-spacing:0.03em;">
                                Today
                            </button>
                        </div>
                        <div class="tab-summary-chips">
                            <span id="chip-sched-count" class="tab-summary-chip" style="color:var(--text-muted);">
                                — scheduled days
                            </span>
                            <button class="btn btn-success sched-add-btn" type="button" id="btn-manage-schedule">
                                <i class="bi bi-plus-lg"></i> Manage Schedule
                            </button>
                        </div>
                    </div>

                    <!-- FullCalendar -->
                    <div class="sched-cal-container">
                        <div id="admin-calendar"></div>
                    </div>

                </div>

                <div class="tab-pane fade" id="tab2" role="tabpanel">

                    <!-- Month nav + summary counts -->
                    <?php
                    $cPresent = $cAbsent = $cIncomplete = 0;
                    foreach ($records as $r) {
                        if ($r['status'] === 'present')    $cPresent++;
                        elseif ($r['status'] === 'absent') $cAbsent++;
                        else                               $cIncomplete++;
                    }
                    ?>
                    <div class="tab-section-header">
                        <div class="d-flex align-items-center gap-2">
                            <button class="sched-nav-btn" onclick="navigatePrev()">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <span class="sched-month-label"><?= htmlspecialchars($monthLabel) ?></span>
                            <button class="sched-nav-btn" onclick="navigateNext()">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div class="tab-summary-chips">
                            <span id="chip-present" class="tab-summary-chip" style="color:var(--primary-color);">
                                <i class="bi bi-check-circle-fill"></i> <?= $cPresent ?> Present
                            </span>
                            <span id="chip-incomplete" class="tab-summary-chip" style="color:var(--warning);">
                                <i class="bi bi-clock-fill"></i> <?= $cIncomplete ?> Incomplete
                            </span>
                            <span id="chip-absent" class="tab-summary-chip" style="color:var(--danger-color);">
                                <i class="bi bi-x-circle-fill"></i> <?= $cAbsent ?> Absent
                            </span>
                        </div>
                    </div>

                    <!-- Gantt chart -->
                    <div class="ganttContainer">
                        <?php
                        $hasRows = false;
                        foreach ($records as $row):
                            $sched    = $schedForGantt[$row['work_date']] ?? null;
                            $ganttBar = computeGanttRow($row, $sched);
                            if ($ganttBar === null) continue;
                            $hasRows = true;
                        ?>

                        <?php if ($ganttBar['type'] === 'absent_or_future'): ?>
                        <div class="ganttRow">
                            <div class="ganttLabel">
                                <div><?= $ganttBar['dayLabel'] ?></div>
                                <div class="ganttSubLabel"><?= $ganttBar['dateNum'] ?></div>
                            </div>
                            <div class="ganttBarContainer"
                                data-range-start="<?= $ganttBar['rangeStart'] ?>"
                                data-range-end="<?= $ganttBar['rangeEnd'] ?>">
                                <?= gantt_cursor() ?>
                                <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>
                                <div class="ganttBar <?= $ganttBar['barClass'] ?>"
                                    style="left:<?= $ganttBar['barLeft'] ?>%; width:<?= $ganttBar['barWidth'] ?>%;">
                                    <span class="<?= $ganttBar['labelClass'] ?>"
                                        style="left:<?= $ganttBar['midLeft'] ?>%;">
                                        <?= $ganttBar['labelText'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php else: ?>
                        <div class="ganttRow">
                            <div class="ganttLabel">
                                <div><?= $ganttBar['dayLabel'] ?></div>
                                <div class="ganttSubLabel"><?= $ganttBar['dateNum'] ?></div>
                            </div>
                            <div class="ganttBarContainer"
                                data-is-today="<?= $ganttBar['isToday'] ? '1' : '0' ?>"
                                data-range-start="<?= $ganttBar['rangeStart'] ?>"
                                data-range-end="<?= $ganttBar['rangeEnd'] ?>"
                                data-sched-in="<?= $ganttBar['schedInLabel'] ?>"
                                data-sched-out="<?= $ganttBar['schedOutLabel'] ?>"
                                data-actual-in="<?= $ganttBar['actualInLabel'] ?>"
                                data-actual-out="<?= $ganttBar['actualOutLabel'] ?>"
                                data-early="<?= $ganttBar['earlyLabel'] ?>"
                                data-late="<?= $ganttBar['lateLabel'] ?>"
                                data-overtime="<?= $ganttBar['overtimeLabel'] ?>"
                                data-overtime-status="<?= $ganttBar['overtimeStatusLabel'] ?>"
                                data-undertime="<?= $ganttBar['undertimeLabel'] ?>"
                                data-overbreak="<?= $ganttBar['overbreakLabel'] ?>">

                                <?= gantt_cursor() ?>
                                <?= gantt_scale($ganttBar['rangeStart'], $ganttBar['rangeEnd']) ?>

                                <?php if ($ganttBar['schedIn'] !== null): ?>
                                    <div class="ganttBar ganttBarScheduled"
                                        style="left:<?= $ganttBar['schedLeft'] ?>%; width:<?= $ganttBar['schedWidth'] ?>%;"></div>
                                <?php endif; ?>

                                <?php if ($ganttBar['isEarly'] && $ganttBar['schedIn']): ?>
                                    <div class="ganttBar ganttBarEarly"
                                        style="left:<?= $ganttBar['earlyLeft'] ?>%; width:<?= $ganttBar['earlyWidth'] ?>%;">
                                        <span class="ganttBarLabel">Early</span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ganttBar['isTardy']): ?>
                                    <div class="ganttBar ganttBarTardy"
                                        style="left:<?= $ganttBar['tardyLeft'] ?>%; width:<?= $ganttBar['tardyWidth'] ?>%;">
                                        <span class="ganttBarLabel">Late</span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ganttBar['hasClockedIn']): ?>
                                    <?php if ($ganttBar['onTimeSplit']): ?>
                                        <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                                            style="left:<?= $ganttBar['actualLeft'] ?>%; width:<?= $ganttBar['onTimeLeftWidth'] ?>%;">
                                            <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                                        </div>
                                        <div class="ganttBar ganttBarBreak"
                                            style="left:<?= $ganttBar['breakLeft'] ?>%; width:<?= $ganttBar['breakWidth'] ?>%;">
                                            <span class="ganttBarLabel">Break</span>
                                        </div>
                                        <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                                            style="left:<?= $ganttBar['onTimeRightLeft'] ?>%; width:<?= $ganttBar['onTimeRightWidth'] ?>%;">
                                            <?php if ($ganttBar['onTimeRightWidth'] > 5): ?>
                                                <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="ganttBar <?= $ganttBar['noTimeOut'] ? 'ganttBarNoTimeOut' : 'ganttBarOnTime' ?>"
                                            style="left:<?= $ganttBar['actualLeft'] ?>%; width:<?= $ganttBar['onTimeWidth'] ?>%;">
                                            <span class="ganttBarLabel"><?= $ganttBar['noTimeOut'] ? 'No Time Out' : 'On Time' ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($ganttBar['isUndertime'] && $ganttBar['schedOut']): ?>
                                        <div class="ganttBar ganttBarUndertime"
                                            style="left:<?= $ganttBar['undertimeLeft'] ?>%; width:<?= $ganttBar['undertimeWidth'] ?>%;">
                                            <span class="ganttBarLabel">Undertime</span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($ganttBar['overtimeMinutes'] > 0 && $ganttBar['schedOut']): ?>
                                    <div class="ganttBar <?= $ganttBar['otColorClass'] ?>"
                                        style="left:<?= $ganttBar['overtimeLeft'] ?>%; width:<?= $ganttBar['overtimeWidth'] ?>%;">
                                        <span class="ganttBarLabel">Overtime</span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($ganttBar['actualInPos'] !== null): ?>
                                    <div class="ganttMarker ganttMarkerActualStart"
                                        style="left:<?= $ganttBar['actualInPos'] ?>%"></div>
                                <?php endif; ?>
                                <?php if ($ganttBar['actualOutPos'] !== null): ?>
                                    <div class="ganttMarker ganttMarkerActualEnd"
                                        style="left:<?= $ganttBar['actualOutPos'] ?>%"></div>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (!$hasRows): ?>
                            <div class="ganttEmpty">
                                <i class="bi bi-calendar-x ganttEmptyIcon"></i>
                                <div>No records found for <?= htmlspecialchars($monthLabel) ?>.</div>
                            </div>
                        <?php endif; ?>
                    </div><!-- .ganttContainer -->

                </div>

                <div class="tab-pane fade" id="tab3" role="tabpanel">

                    <!-- Filter bar -->
                    <div class="ev-logs-filter">
                        <div class="dropdown">
                            <button class="btn dropdown-toggle" id="logsDatePickerBtn" type="button">
                                <i class="bi bi-calendar3"></i>
                                <span id="logsDateRangeLabel"><?= htmlspecialchars($monthLabel) ?></span>
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn dropdown-toggle" type="button" id="logsTypeToggle"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-funnel"></i>
                                <span id="logsTypeLabel">All Types</span>
                            </button>
                            <ul class="dropdown-menu" id="logsTypeMenu">
                                <li><a class="dropdown-item" href="#" data-value="ALL">All Types</a></li>
                                <li><a class="dropdown-item" href="#" data-value="IN">Time In</a></li>
                                <li><a class="dropdown-item" href="#" data-value="OUT">Time Out</a></li>
                                <li><a class="dropdown-item" href="#" data-value="BREAK_IN">Break In</a></li>
                                <li><a class="dropdown-item" href="#" data-value="BREAK_OUT">Break Out</a></li>
                            </ul>
                        </div>
                        <span id="chip-logs-count" class="tab-summary-chip ms-auto" style="color:var(--text-muted);">—</span>
                    </div>

                    <!-- Sticky header -->
                    <div class="ev-logs-header-glass">
                        <table class="table table-borderless mb-0">
                            <colgroup>
                                <col style="width:17%">
                                <col style="width:11%">
                                <col style="width:14%">
                                <col style="width:18%">
                                <col style="width:15%">
                                <col style="width:13%">
                                <col style="width:12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="logs-sortable" data-sort="date">Date <i class="bi bi-arrow-down-up logs-sort-icon" id="lsort-date"></i></th>
                                    <th class="logs-sortable" data-sort="time">Time <i class="bi bi-arrow-down-up logs-sort-icon" id="lsort-time"></i></th>
                                    <th class="logs-sortable" data-sort="type">Log Type <i class="bi bi-arrow-down-up logs-sort-icon" id="lsort-type"></i></th>
                                    <th class="logs-sortable" data-sort="location">Location <i class="bi bi-arrow-down-up logs-sort-icon" id="lsort-location"></i></th>
                                    <th>Requested By</th>
                                    <th>Edit Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>

                    <!-- Scrollable body -->
                    <div class="ev-logs-scroll">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col style="width:17%">
                                <col style="width:11%">
                                <col style="width:14%">
                                <col style="width:18%">
                                <col style="width:15%">
                                <col style="width:13%">
                                <col style="width:12%">
                            </colgroup>
                            <tbody id="admin_logs_tbody"></tbody>
                        </table>
                    </div>

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

<!-- ===== MAP POPUP ===== -->
<div class="mapPopUpContainer" id="ev-map-popup-container">
    <div class="mapPopUp" id="ev-map-popup"></div>
    <div class="mapPopUpInfo" id="ev-map-popup-info"></div>
    <div style="padding:10px;">
        <a class="openGoogleMapsBtn" id="ev-map-gmaps-btn" href="#" target="_blank">Open in Google Maps</a>
    </div>
</div>

<!-- ===== GANTT TOOLTIP ===== -->
<div id="gantt_tooltip">
    <div class="ganttToolTipRow">
        <span class="ganttToolTipLabel">Scheduled</span>
        <span class="ganttToolTipValue" id="gt-sched"></span>
    </div>
    <div class="ganttToolTipRow">
        <span class="ganttToolTipLabel">Time In</span>
        <span class="ganttToolTipValue" id="gt-actual-in"></span>
    </div>
    <div class="ganttToolTipRow">
        <span class="ganttToolTipLabel">Time Out</span>
        <span class="ganttToolTipValue" id="gt-actual-out"></span>
    </div>
    <div class="ganttToolTipRow ganttToolTipEarly" id="gt-early-row">
        <span class="ganttToolTipLabel">Early</span>
        <span class="ganttToolTipValue" id="gt-early"></span>
    </div>
    <div class="ganttToolTipRow ganttToolTipLate" id="gt-late-row">
        <span class="ganttToolTipLabel">Late</span>
        <span class="ganttToolTipValue" id="gt-late"></span>
    </div>
    <div class="ganttToolTipRow ganttToolTipOverBreak" id="gt-ob-row">
        <span class="ganttToolTipLabel">Overbreak</span>
        <span class="ganttToolTipValue" id="gt-ob"></span>
    </div>
    <div class="ganttToolTipRow ganttToolTipOverTime" id="gt-ot-row">
        <span class="ganttToolTipLabel" id="gt-ot-label">Overtime</span>
        <span class="ganttToolTipValue" id="gt-ot"></span>
    </div>
    <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row">
        <span class="ganttToolTipLabel">Undertime</span>
        <span class="ganttToolTipValue" id="gt-ut"></span>
    </div>
</div>

<!-- ===== MANAGE SCHEDULE MODAL (merged) ===== -->

<div class="modal fade" id="manageScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-week me-2"></i>Manage Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="admin_employee_view.php?employee_id=<?= htmlspecialchars($urlEmpId) ?>" id="addSchedForm">
                <input type="hidden" name="action" value="save_combined">
                <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                <input type="hidden" name="selected_dates" id="addSelectedDatesInput">
                <input type="hidden" name="rest_days" id="restDaysInput" value="[]">
                <input type="hidden" name="rest_days_dirty" id="restDaysDirty" value="0">
                <input type="hidden" name="single_rest_dates" id="singleRestDatesInput" value="[]">

                <div class="modal-body">
                    <!-- Preset Schedule -->
                    <div class="preset-sched-dropdown-wrap mb-3">
                        <label class="form-label">Preset Schedule</label>
                        <button type="button" class="preset-sched-trigger" id="presetSchedTrigger">
                            <span id="presetSchedDisplay">Select a preset schedule...</span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                <div class="preset-sched-menu" id="presetSchedMenu"></div>
                <script>
                    const presetSchedMenu = document.getElementById("presetSchedMenu");

                    // SETTINGS
                    const intervalMinutes = 30;
                    const shiftHours = 9;

                    // 6:00 AM up to 5:30 AM next day
                    const startMinutes = 6 * 60; // 6:00 AM
                    const endMinutes = (24 * 60) + (5 * 60) + 30;

                    function formatTime(hour, minute) {
                        const period = hour >= 12 ? "PM" : "AM";

                        let displayHour = hour % 12;

                        if (displayHour === 0) {
                            displayHour = 12;
                        }

                        return `${displayHour}:${minute
                            .toString()
                            .padStart(2, "0")} ${period}`;
                    }

                    function to24Hour(hour, minute) {
                        return `${hour.toString().padStart(2, "0")}:${minute
                            .toString()
                            .padStart(2, "0")}`;
                    }

                    for (
                        let totalMinutes = startMinutes;
                        totalMinutes <= endMinutes;
                        totalMinutes += intervalMinutes
                    ) {

                        // Normalize current time
                        const currentMinutes = totalMinutes % (24 * 60);

                        const inHour = Math.floor(currentMinutes / 60);
                        const inMinute = currentMinutes % 60;

                        // OUT TIME (+9 hours)
                        let outTotalMinutes = currentMinutes + (shiftHours * 60);

                        // Wrap next day
                        outTotalMinutes = outTotalMinutes % (24 * 60);

                        const outHour = Math.floor(outTotalMinutes / 60);
                        const outMinute = outTotalMinutes % 60;

                        // CREATE ITEM
                        const item = document.createElement("div");

                        item.className = "preset-sched-item";

                        item.dataset.in = to24Hour(inHour, inMinute);
                        item.dataset.out = to24Hour(outHour, outMinute);

                        item.textContent =
                            `${formatTime(inHour, inMinute)} – ${formatTime(outHour, outMinute)}`;

                        presetSchedMenu.appendChild(item);
                    }
                </script>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Time In</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                <input type="time" name="time_in" id="addModalTimeIn" class="form-control">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out <small class="text-muted">(next day if night)</small></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                <input type="time" name="time_out" id="addModalTimeOut" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Dates</label>
                        <input type="text" id="addSchedDatePicker" class="form-control" placeholder="Click to select dates..." readonly>
                        <div id="addSelectedDatesList" class="mt-2"></div>
                    </div>

                    <div id="restDaySection">
                        <label class="form-label">Set Rest Days</label>
                        <div class="rest-day-grid" id="restDayToggles">
                            <button type="button" class="btn rest-day-toggle" data-dow="0">Sun</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="1">Mon</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="2">Tue</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="3">Wed</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="4">Thu</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="5">Fri</button>
                            <button type="button" class="btn rest-day-toggle" data-dow="6">Sat</button>
                        </div>
                    </div>

                    <div id="singleDateRestDaySection" style="display:none;">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="isSingleRestDay">
                            <label class="form-check-label" for="isSingleRestDay">Is Rest Day</label>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle-fill me-1"></i> Save Schedule
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- ===== SCHEDULE ADD / EDIT MODAL ===== -->
<div class="modal fade" id="schedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-modal">

            <div class="modal-header">
                <h5 class="modal-title" id="schedModalTitle">Add Schedule</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeSchedModal()"></button>
            </div>

            <form method="POST" action="admin_employee_view.php?employee_id=<?= htmlspecialchars($urlEmpId) ?>" id="schedForm">
                <input type="hidden" name="action" value="save_schedule">
                <div class="modal-body">

                    <input type="hidden" name="employee_id" id="modalEmpId" value="<?= $employeeId ?>">
                    <input type="hidden" name="selected_dates" id="selectedDatesInput">
                    <input type="hidden" name="is_edit" id="isEditMode" value="0">
                    <input type="hidden" name="is_rest_day" id="modalIsRestDay" value="0">

                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <input type="text" id="modalEmpName"
                               class="form-control"
                               value="<?= htmlspecialchars($emp['name']) ?>"
                               readonly>
                    </div>

                    <div class="form-check mb-3" id="restDayCheckRow">
                        <input class="form-check-input" type="checkbox" id="modalRestDayCheck">
                        <label class="form-check-label" for="modalRestDayCheck">Mark as Rest Day</label>
                    </div>

                    <div id="modalTimeFields" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Time In</label>
                            <input type="time" name="time_in" id="modalTimeIn" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Time Out <small class="text-muted">(next day if night shift)</small>
                            </label>
                            <input type="time" name="time_out" id="modalTimeOut" class="form-control" required>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Select Dates</label>
                        <p class="text-muted small mb-2">Click to select/deselect work days.</p>
                        <input type="text" id="schedDatePicker" class="form-control" readonly>
                        <div id="selectedDatesList" class="mt-2"></div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <span id="schedSubmitLabel">Save Schedule</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>


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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../system_functions/gantt.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
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
let selectedDates    = [];
let selectedDatesAdd = [];
let selectedRestDays = [];
let fp               = null;
let fpAdd            = null;
const EMP_ID         = <?= $employeeId ?>;
const EMP_URL_ID     = '<?= htmlspecialchars($urlEmpId) ?>';
let currentMonth  = '<?= $rawMonth ?>';
const tabLoadedMonth = { '#tab1': null, '#tab2': null, '#tab3': null };
let adminCalendar    = null;
let scheduledDates   = new Set();
let _suppressDatesSet = false;

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
function loadingHTML() {
    return '<div class="text-center py-5" style="color:var(--text-muted);"><i class="bi bi-arrow-clockwise" style="font-size:1.5rem;"></i></div>';
}

// ---- Month navigation (delegates to FullCalendar; datesSet syncs state) ----
function navigatePrev()  { if (adminCalendar) adminCalendar.prev(); }
function navigateNext()  { if (adminCalendar) adminCalendar.next(); }
function navigateToday() { if (adminCalendar) adminCalendar.today(); }

// ---- Load tab by target ----
function loadTab(tabTarget, ym) {
    const { startDate, endDate } = monthDates(ym);
    if      (tabTarget === '#tab1') { if (adminCalendar) adminCalendar.updateSize(); }
    else if (tabTarget === '#tab2') loadRecords(startDate, endDate);
    else if (tabTarget === '#tab3') loadLogs(startDate, endDate);
}


// ---- Tab 2: Records / Gantt ----
function loadRecords(startDate, endDate) {
    const container = document.querySelector('.ganttContainer');
    container.innerHTML = loadingHTML();
    fetch(`get_admin_employee_records.php?employee_id=${EMP_ID}&start=${startDate}&end=${endDate}`)
        .then(r => r.text())
        .then(html => {
            const match = html.match(/<!--SUMMARY:(\{.*?\})-->/);
            if (match) {
                try {
                    const c = JSON.parse(match[1]);
                    document.getElementById('chip-present').innerHTML    = `<i class="bi bi-check-circle-fill"></i> ${c.present} Present`;
                    document.getElementById('chip-incomplete').innerHTML = `<i class="bi bi-clock-fill"></i> ${c.incomplete} Incomplete`;
                    document.getElementById('chip-absent').innerHTML     = `<i class="bi bi-x-circle-fill"></i> ${c.absent} Absent`;
                } catch (e) {}
            }
            container.innerHTML = html;
            initGanttCursors();
        })
        .catch(() => {
            container.innerHTML = '<div class="ganttEmpty"><i class="bi bi-exclamation-circle ganttEmptyIcon"></i><div>Failed to load records.</div></div>';
        });
}

// ---- Tab 3: Logs state ----
let logStartDate = '<?= $monthStart ?>';
let logEndDate   = '<?= $monthEnd ?>';
let logType      = 'ALL';
let logSort      = 'date';
let logSortDir   = 'desc';
let fpLogs       = null;

function loadLogs(start, end) {
    if (start) logStartDate = start;
    if (end)   logEndDate   = end;
    if (fpLogs) {
        fpLogs.setDate([logStartDate, logEndDate], false);
        updateLogsDateLabel([new Date(logStartDate + 'T00:00:00'), new Date(logEndDate + 'T00:00:00')]);
    }
    fetchAdminLogs();
}

function fetchAdminLogs() {
    const tbody = document.getElementById('admin_logs_tbody');
    tbody.innerHTML = `<tr class="emptyRow"><td colspan="7"><div class="logsEmpty"><i class="bi bi-arrow-clockwise" style="font-size:1.5rem;"></i></div></td></tr>`;
    fetch(`../get_logs.php?employee_id=${EMP_ID}&start=${logStartDate}&end=${logEndDate}&type=${logType}&sort=${logSort}&dir=${logSortDir}`)
        .then(r => r.json())
        .then(data => {
            const rows = data.rows ?? [];
            if (!rows.length) {
                tbody.innerHTML = `<tr class="emptyRow"><td colspan="7"><div class="logsEmpty"><i class="bi bi-calendar-x logsEmptyIcon"></i><div>No logs found for this period.</div></div></td></tr>`;
                const chip = document.getElementById('chip-logs-count');
                if (chip) chip.textContent = '0 logs';
                return;
            }

            const LOG_TYPE_CLASS = { IN: 'btn-success', OUT: 'btn-danger', BREAK_IN: 'status-pending', BREAK_OUT: 'btn-info' };
            const LOG_TYPE_LABEL = { IN: 'Time In', OUT: 'Time Out', BREAK_IN: 'Break In', BREAK_OUT: 'Break Out' };

            function escHtml(v) {
                if (v == null) return '';
                return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            tbody.innerHTML = rows.map(row => {
                const isInside  = row.is_within_office;
                const locLabel  = isInside ? 'Within Office' : 'Outside Office';
                const locClass  = isInside ? 'btn-success' : 'btn-danger';
                const acc       = row.accuracy        != null ? row.accuracy        : 'N/A';
                const dist      = row.distance_meters != null ? row.distance_meters : 'N/A';
                const typeClass = LOG_TYPE_CLASS[row.log_type] ?? '';
                const typeLabel = LOG_TYPE_LABEL[row.log_type] ?? row.log_type;

                let editRoleHtml = `<span style="color:rgba(255,255,255,0.15);font-size:0.75rem;">—</span>`;
                if (row.edit_role === 'admin') {
                    editRoleHtml = `<span class="pill empRole-admin"><i class="bi bi-shield-fill"></i> ${escHtml(row.initiator_name ?? 'Admin')}</span>`;
                } else if (row.edit_role === 'employee') {
                    editRoleHtml = `<span class="pill"><i class="bi bi-person-fill"></i> ${escHtml(row.initiator_name ?? 'Employee')}</span>`;
                }

                let editStatusHtml = `<span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>`;
                if (row.edit_status === 'pending') {
                    editStatusHtml = `<span class="pill btn-info"><i class="bi bi-hourglass-split"></i> Pending</span>`;
                } else if (row.edit_status === 'approved') {
                    editStatusHtml = `<span class="pill btn-success"><i class="bi bi-check-circle-fill"></i> Approved</span>`;
                } else if (row.edit_status === 'rejected') {
                    editStatusHtml = `<span class="pill btn-danger"><i class="bi bi-x-circle-fill"></i> Rejected</span>`;
                }

                return `<tr>
                    <td>${escHtml(row.date)}</td>
                    <td>${escHtml(row.time)}</td>
                    <td><span class="pill ${typeClass}">${typeLabel}</span></td>
                    <td>
                        <a href="https://www.google.com/maps?q=${row.latitude},${row.longitude}" target="_blank"
                            class="pill ${locClass} loc-trigger"
                            style="text-decoration:none;"
                            data-lat="${escHtml(row.latitude)}"
                            data-lng="${escHtml(row.longitude)}"
                            data-label="${escHtml(locLabel)}"
                            data-acc="${escHtml(acc)}"
                            data-dist="${escHtml(dist)}">
                            <i class="bi bi-geo-alt-fill"></i>
                            ${locLabel}
                        </a>
                    </td>
                    <td>${editRoleHtml}</td>
                    <td>${editStatusHtml}</td>
                    <td>
                        <button class="leEditRowBtn" title="Edit log entry"
                            data-log-id="${row.log_id}"
                            data-log-type="${escHtml(row.log_type)}"
                            data-log-datetime="${escHtml(row.log_datetime)}"
                            data-log-date-label="${escHtml(row.date)}"
                            data-log-time-label="${escHtml(row.time)}"
                            onclick="openAdminLogEditModal(this)">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

            const count = rows.length;
            const chip  = document.getElementById('chip-logs-count');
            if (chip) chip.textContent = count + ' log' + (count !== 1 ? 's' : '');
        })
        .catch(() => {
            tbody.innerHTML = `<tr class="emptyRow"><td colspan="7"><div class="logsEmpty"><i class="bi bi-exclamation-circle logsEmptyIcon"></i><div>Failed to load logs.</div></div></td></tr>`;
        });
}

function updateLogsDateLabel(dates) {
    const el = document.getElementById('logsDateRangeLabel');
    if (!el || !dates.length) return;
    const fmt = d => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const same = dates.length > 1 && dates[0].toDateString() === dates[1].toDateString();
    el.textContent = (dates.length === 1 || same) ? fmt(dates[0]) : fmt(dates[0]) + ' – ' + fmt(dates[1]);
}

function applyLogsHeaderUI() {
    document.querySelectorAll('.logs-sortable').forEach(el => el.classList.remove('sorted'));
    document.querySelectorAll('.logs-sort-icon').forEach(el => { el.className = 'logs-sort-icon bi bi-arrow-down-up'; });
    const activeTh = document.querySelector(`.logs-sortable[data-sort="${logSort}"]`);
    if (activeTh) {
        activeTh.classList.add('sorted');
        const icon = activeTh.querySelector('.logs-sort-icon');
        if (icon) icon.className = 'logs-sort-icon bi ' + (logSortDir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down');
    }
}

// ---- Map popup ----
let popupMap    = null;
let hideTimeout = null;
const mapPopup  = document.getElementById('ev-map-popup-container');

document.addEventListener('mouseover', e => {
    const trigger = e.target.closest('.loc-trigger');
    if (!trigger || !mapPopup) return;
    clearTimeout(hideTimeout);
    const lat = parseFloat(trigger.dataset.lat), lng = parseFloat(trigger.dataset.lng);
    document.getElementById('ev-map-gmaps-btn').href = `https://www.google.com/maps?q=${lat},${lng}`;
    const rect = trigger.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom, spaceAbove = rect.top;
    const topPos  = (spaceBelow < 320 && spaceAbove > spaceBelow) ? rect.top + window.scrollY - 323 : rect.bottom + window.scrollY + 3;
    const leftPos = (window.innerWidth - rect.left < 300) ? rect.right + window.scrollX - 610 : rect.left + window.scrollX - 310;
    mapPopup.style.top     = `${topPos}px`;
    mapPopup.style.left    = `${leftPos}px`;
    mapPopup.style.display = 'block';
    document.getElementById('ev-map-popup-info').innerHTML =
        `<b>${trigger.dataset.label}</b><br>Lat: ${lat} &nbsp; Lng: ${lng}<br>Accuracy: ±${trigger.dataset.acc} m &nbsp; Distance: ${trigger.dataset.dist} m`;
    setTimeout(() => {
        if (!popupMap) {
            popupMap = L.map('ev-map-popup', { zoomControl: false, attributionControl: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(popupMap);
            popupMap._marker = null;
        }
        popupMap.invalidateSize();
        popupMap.setView([lat, lng], 17);
        if (popupMap._marker) popupMap.removeLayer(popupMap._marker);
        popupMap._marker = L.marker([lat, lng]).addTo(popupMap);
    }, 50);
});

document.addEventListener('mouseout', e => {
    if (!e.target.closest('.loc-trigger')) return;
    hideTimeout = setTimeout(() => {
        if (mapPopup) mapPopup.style.display = 'none';
        if (popupMap) { popupMap.remove(); popupMap = null; }
    }, 200);
});

if (mapPopup) {
    mapPopup.addEventListener('mouseover', () => clearTimeout(hideTimeout));
    mapPopup.addEventListener('mouseout', () => {
        hideTimeout = setTimeout(() => {
            mapPopup.style.display = 'none';
            if (popupMap) { popupMap.remove(); popupMap = null; }
        }, 200);
    });
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

    // ---- FullCalendar (Tab 1) ----
    let _skipDateClick = false;
    adminCalendar = new FullCalendar.Calendar(document.getElementById('admin-calendar'), {
        initialView:  'dayGridMonth',
        firstDay:     0,
        headerToolbar: false,
        height:       'auto',
        initialDate:  '<?= sprintf('%04d-%02d-01', $viewYear, $viewMonthNum) ?>',
        dayMaxEvents: false,
        eventDisplay: 'block',

        events: {
            url:         'get_admin_employee_calendar.php',
            method:      'GET',
            extraParams: { employee_id: EMP_ID },
            failure:     function() { console.error('Failed to fetch schedule events.'); }
        },

        datesSet: function(info) {
            if (_suppressDatesSet) { _suppressDatesSet = false; return; }
            const d    = info.view.currentStart;
            const newYM = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
            if (newYM === currentMonth) return;
            currentMonth = newYM;
            document.querySelectorAll('.sched-month-label').forEach(el => el.textContent = monthLabel(newYM));
            history.pushState({ month: newYM }, '', `?employee_id=${EMP_URL_ID}&month=${newYM}`);
            Object.keys(tabLoadedMonth).forEach(k => { tabLoadedMonth[k] = k === '#tab1' ? newYM : null; });
            const activeBtn2 = document.querySelector('#myTab .nav-link.active');
            const activeTab2 = activeBtn2 ? activeBtn2.dataset.bsTarget : '#tab1';
            if (activeTab2 !== '#tab1') { loadTab(activeTab2, newYM); tabLoadedMonth[activeTab2] = newYM; }
        },

        eventsSet: function(events) {
            scheduledDates = new Set(
                events.filter(e => ['day', 'night', 'rest'].includes(e.extendedProps.type)).map(e => e.startStr)
            );
            const count = scheduledDates.size;
            const chip  = document.getElementById('chip-sched-count');
            if (chip) chip.textContent = count + ' scheduled day' + (count !== 1 ? 's' : '');

            // Show add-overlay on days (including other-month days) that have no events
            const eventDates = new Set(events.map(e => e.startStr));
            document.querySelectorAll('#admin-calendar .fc-daygrid-day').forEach(cell => {
                cell.classList.toggle('fc-day-has-events', eventDates.has(cell.dataset.date));
            });
        },

        dayCellDidMount: function(info) {
            const frame = info.el.querySelector('.fc-daygrid-day-frame');
            if (!frame) return;
            const overlay = document.createElement('div');
            overlay.className = 'fc-day-add-overlay';
            overlay.innerHTML = '<i class="bi bi-plus-circle"></i>';
            frame.appendChild(overlay);
        },

        eventContent: function(arg) {
            const props = arg.event.extendedProps;
            let html = '<div class="fc-admin-inner">';
            html += `<span class="fc-admin-label">${arg.event.title}</span>`;
            if (props.timeInStr && props.timeOutStr) {
                html += `<span class="fc-admin-time">${props.timeInStr} - ${props.timeOutStr}</span>`;
            }
            html += '</div>';
            return { html };
        },

        eventDidMount: function(info) {
            const props   = info.event.extendedProps;
            const type    = props.type;
            const canEdit = !props.hasActiveLeaveOrOB && props.hasSchedule &&
                            ['day', 'night', 'rest', 'leave-rejected', 'pending-schedule'].includes(type);
            const canDel  = ['day', 'night', 'rest', 'pending-schedule'].includes(type);
            if (!canEdit && !canDel) return;

            // Attach buttons to the day cell frame so they sit at the bottom-right
            // of the date, not inside the event element
            const cell  = info.el.closest('.fc-daygrid-day');
            const frame = cell ? cell.querySelector('.fc-daygrid-day-frame') : null;
            if (!frame || frame.querySelector('.fc-ev-actions')) return; // avoid duplicates

            const wrap = document.createElement('div');
            wrap.className = 'fc-ev-actions';

            if (canEdit) {
                const btn = document.createElement('button');
                btn.className = 'sched-cal-action-btn edit';
                btn.title = 'Edit';
                btn.innerHTML = '<i class="bi bi-pencil"></i>';
                btn.addEventListener('click', e => {
                    e.stopPropagation();
                    _skipDateClick = true;
                    setTimeout(() => { _skipDateClick = false; }, 100);
                    if (props.type === 'rest' || props.isRestDay) openRestDayEditModal(props.dateStr);
                    else openEditModal(props.dateStr, props.schedInVal || '', props.schedOutVal || '');
                });
                wrap.appendChild(btn);
            }
            if (canDel) {
                const btn = document.createElement('button');
                btn.className = 'sched-cal-action-btn delete';
                btn.title = 'Delete';
                btn.innerHTML = '<i class="bi bi-trash"></i>';
                btn.addEventListener('click', e => { e.stopPropagation(); deleteSchedule(props.dateStr); });
                wrap.appendChild(btn);
            }
            frame.appendChild(wrap);
        },

        dateClick: function(info) {
            if (_skipDateClick) return;
            openManageModalWithDate(info.dateStr);
        },
    });

    adminCalendar.render();
    tabLoadedMonth['#tab1'] = currentMonth;

    initGanttCursors();

    const activeBtn = document.querySelector('#myTab .nav-link.active');
    const activeTab = activeBtn ? activeBtn.dataset.bsTarget : '#tab1';
    loadTab(activeTab, currentMonth);
    tabLoadedMonth[activeTab] = currentMonth;

    // ---- Logs tab: flatpickr, type filter, sort headers ----
    fpLogs = flatpickr('#logsDatePickerBtn', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        defaultDate: [logStartDate, logEndDate],
        onReady(dates) { updateLogsDateLabel(dates); },
        onChange(dates) {
            updateLogsDateLabel(dates);
            if (dates.length !== 2) return;
            const pad = n => String(n).padStart(2, '0');
            const toLocal = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            logStartDate = toLocal(dates[0]);
            logEndDate   = toLocal(dates[1]);
            fetchAdminLogs();
        }
    });

    document.querySelectorAll('#logsTypeMenu .dropdown-item').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            document.getElementById('logsTypeLabel').textContent = item.textContent.trim();
            logType = item.dataset.value;
            fetchAdminLogs();
        });
    });

    applyLogsHeaderUI();
    document.querySelectorAll('.logs-sortable').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.sort;
            if (logSort === col) {
                logSortDir = logSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                logSort    = col;
                logSortDir = 'asc';
            }
            applyLogsHeaderUI();
            fetchAdminLogs();
        });
    });

    fp = flatpickr('#schedDatePicker', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        onChange(dates) {
            if (dates.length < 2) {
                selectedDates = dates.length === 1
                    ? [`${dates[0].getFullYear()}-${pad(dates[0].getMonth()+1)}-${pad(dates[0].getDate())}`]
                    : [];
                renderDateTags();
                return;
            }
            selectedDates = [];
            const cur = new Date(dates[0].getTime());
            const end = new Date(dates[1].getTime());
            while (cur <= end) {
                selectedDates.push(`${cur.getFullYear()}-${pad(cur.getMonth()+1)}-${pad(cur.getDate())}`);
                cur.setDate(cur.getDate() + 1);
            }
            renderDateTags();
        }
    });

    fpAdd = flatpickr('#addSchedDatePicker', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        onChange(dates) {
            if (dates.length < 2) {
                selectedDatesAdd = dates.length === 1
                    ? [`${dates[0].getFullYear()}-${pad(dates[0].getMonth()+1)}-${pad(dates[0].getDate())}`]
                    : [];
                renderDateTagsAdd();
                return;
            }
            selectedDatesAdd = [];
            const cur = new Date(dates[0].getTime());
            const end = new Date(dates[1].getTime());
            while (cur <= end) {
                selectedDatesAdd.push(`${cur.getFullYear()}-${pad(cur.getMonth()+1)}-${pad(cur.getDate())}`);
                cur.setDate(cur.getDate() + 1);
            }
            renderDateTagsAdd();
        }
    });

    // ---- Manage Schedule button ----
    document.getElementById('btn-manage-schedule').addEventListener('click', () => {
        openManageModal();
    });

    // ---- Rest day weekday toggles ----
    document.querySelectorAll('.rest-day-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const dow = parseInt(btn.dataset.dow);
            if (btn.classList.contains('active')) {
                btn.classList.remove('active');
                selectedRestDays = selectedRestDays.filter(d => d !== dow);
            } else {
                if (selectedRestDays.length >= 2) return;
                btn.classList.add('active');
                selectedRestDays.push(dow);
            }
            document.getElementById('restDaysDirty').value = '1';
        });
    });

    // ---- Manage Schedule form (merged: dates + rest days) ----
    document.getElementById('addSchedForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const isSingleRest   = selectedDatesAdd.length === 1 && document.getElementById('isSingleRestDay').checked;
        const datesToSchedule = isSingleRest ? [] : [...selectedDatesAdd];
        const singleRestDates = isSingleRest ? [...selectedDatesAdd] : [];

        const hasDates      = datesToSchedule.length > 0;
        const hasRestDays   = document.getElementById('restDaysDirty').value === '1';
        const hasSingleRest = singleRestDates.length > 0;

        if (!hasDates && !hasRestDays && !hasSingleRest) {
            showToast('Please select dates or set rest days.', 'warning');
            return;
        }

        if (hasDates) {
            const conflicts = datesToSchedule.filter(d => scheduledDates.has(d));
            if (conflicts.length > 0) {
                const msg = conflicts.length === 1
                    ? `A schedule for ${conflicts[0]} already exists. Replace it?`
                    : `Schedules for ${conflicts.length} selected dates already exist. Replace them?`;
                if (!confirm(msg)) return;
            }
        }

        document.getElementById('addSelectedDatesInput').value = JSON.stringify(datesToSchedule);
        document.getElementById('restDaysInput').value         = JSON.stringify(selectedRestDays);
        document.getElementById('singleRestDatesInput').value  = JSON.stringify(singleRestDates);

        fetch(this.getAttribute('action'), { method: 'POST', body: new FormData(this) })
            .then(r => {
                if (!r.ok && r.status !== 200) throw new Error('save failed');
                bootstrap.Modal.getInstance(document.getElementById('manageScheduleModal'))?.hide();
                showToast('Schedule saved successfully', 'success');
                if (adminCalendar) adminCalendar.refetchEvents();
            })
            .catch(() => showToast('Failed to save schedule. Please try again.', 'danger'));
    });

    // ---- Edit Schedule form (inside Edit modal, opened from calendar pencil) ----
    document.getElementById('schedForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!prepareSubmit()) return;
        const action = this.getAttribute('action');
        fetch(action, { method: 'POST', body: new FormData(this) })
            .then(r => {
                if (!r.ok && r.status !== 200) throw new Error('save failed');
                closeSchedModal();
                showToast('Schedule saved successfully', 'success');
                if (adminCalendar) adminCalendar.refetchEvents();
            })
            .catch(() => showToast('Failed to save schedule. Please try again.', 'danger'));
    });

    // ---- Rest Day checkbox in edit modal ----
    document.getElementById('modalRestDayCheck').addEventListener('change', function () {
        const isRest = this.checked;
        document.getElementById('modalIsRestDay').value          = isRest ? '1' : '0';
        document.getElementById('modalTimeFields').style.display = isRest ? 'none' : '';
        document.getElementById('modalTimeIn').required          = !isRest;
        document.getElementById('modalTimeOut').required         = !isRest;
    });

    // Browser back / forward
    window.addEventListener('popstate', e => {
        if (e.state && e.state.month) {
            const ym = e.state.month;
            currentMonth = ym;
            document.querySelectorAll('.sched-month-label').forEach(el => el.textContent = monthLabel(ym));
            if (adminCalendar) {
                _suppressDatesSet = true;
                const [y, m] = ym.split('-').map(Number);
                adminCalendar.gotoDate(new Date(y, m - 1, 1));
            }
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
                fetchAdminLogs();
            } else {
                showToast(data.message || 'Failed to update log.', 'danger');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'danger'));
    });

});

// ---- Manage Schedule modal ----
function openManageModal() {
    document.getElementById('addModalTimeIn').value  = '';
    document.getElementById('addModalTimeOut').value = '';
    document.querySelectorAll('.preset-sched-item').forEach(el => el.classList.remove('active'));
    document.getElementById('presetSchedDisplay').textContent = 'Select a preset schedule...';
    document.getElementById('presetSchedMenu').classList.remove('open');
    selectedDatesAdd = [];
    renderDateTagsAdd();
    if (fpAdd) fpAdd.clear();
    selectedRestDays = [];
    document.querySelectorAll('.rest-day-toggle').forEach(btn => btn.classList.remove('active'));
    document.getElementById('restDaysDirty').value              = '0';
    document.getElementById('isSingleRestDay').checked          = false;
    document.getElementById('singleDateRestDaySection').style.display = 'none';
    document.getElementById('restDaySection').style.display           = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('manageScheduleModal')).show();
}

// ---- Preset Schedule ----
(function initPresetSchedule() {
    const trigger = document.getElementById('presetSchedTrigger');
    const menu    = document.getElementById('presetSchedMenu');

    trigger.addEventListener('click', e => {
        e.stopPropagation();
        menu.classList.toggle('open');
    });

    document.querySelectorAll('.preset-sched-item').forEach(item => {
        item.addEventListener('click', () => {
            document.getElementById('addModalTimeIn').value  = item.dataset.in;
            document.getElementById('addModalTimeOut').value = item.dataset.out;
            document.querySelectorAll('.preset-sched-item').forEach(el => el.classList.remove('active'));
            item.classList.add('active');
            document.getElementById('presetSchedDisplay').textContent = item.textContent;
            menu.classList.remove('open');
        });
    });

    document.addEventListener('click', () => menu.classList.remove('open'));
})();

function openManageModalWithDate(dateStr) {
    openManageModal();
    selectedDatesAdd = [dateStr];
    if (fpAdd) fpAdd.setDate([dateStr, dateStr], false);
    renderDateTagsAdd();
}

function openRestDayEditModal(dateStr) {
    openManageModalWithDate(dateStr);
    document.getElementById('isSingleRestDay').checked = true;
}

// ---- Schedule modal helpers ----
function renderDateTagsAdd() {
    const list = document.getElementById('addSelectedDatesList');
    if (selectedDatesAdd.length === 0) {
        list.innerHTML = '';
    } else if (selectedDatesAdd.length === 1) {
        list.innerHTML = `<span class="selected-date-tag">${selectedDatesAdd[0]}
            <span class="selected-date-remove" onclick="clearDateSelectionAdd()">&times;</span>
        </span>`;
    } else {
        const first = selectedDatesAdd[0], last = selectedDatesAdd[selectedDatesAdd.length - 1];
        list.innerHTML = `<span class="selected-date-tag">
            ${first} &rarr; ${last} &nbsp;(${selectedDatesAdd.length} days)
            <span class="selected-date-remove" onclick="clearDateSelectionAdd()">&times;</span>
        </span>`;
    }
    updateRestDaySection();
}

function updateRestDaySection() {
    const single = selectedDatesAdd.length === 1;
    document.getElementById('restDaySection').style.display           = single ? 'none' : '';
    document.getElementById('singleDateRestDaySection').style.display = single ? ''     : 'none';
    if (!single) document.getElementById('isSingleRestDay').checked   = false;
}

function clearDateSelectionAdd() {
    selectedDatesAdd = [];
    if (fpAdd) fpAdd.clear();
    renderDateTagsAdd();
}

function renderDateTags() {
    const list = document.getElementById('selectedDatesList');
    if (selectedDates.length === 0) { list.innerHTML = ''; return; }
    if (selectedDates.length === 1) {
        list.innerHTML = `<span class="selected-date-tag">${selectedDates[0]}
            <span class="selected-date-remove" onclick="clearDateSelection()">&times;</span>
        </span>`;
    } else {
        const first = selectedDates[0], last = selectedDates[selectedDates.length - 1];
        list.innerHTML = `<span class="selected-date-tag">
            ${first} &rarr; ${last} &nbsp;(${selectedDates.length} days)
            <span class="selected-date-remove" onclick="clearDateSelection()">&times;</span>
        </span>`;
    }
}

function clearDateSelection() {
    selectedDates = [];
    if (fp) fp.clear();
    renderDateTags();
}

function openAddModal() {
    document.getElementById('schedModalTitle').textContent  = 'Add Schedule';
    document.getElementById('schedSubmitLabel').textContent = 'Save Schedule';
    document.getElementById('isEditMode').value             = '0';
    document.getElementById('modalTimeIn').value            = '';
    document.getElementById('modalTimeOut').value           = '';
    selectedDates = [];
    renderDateTags();
    if (fp) fp.clear();
}

function openEditModal(empIdOrDate, dateOrTimeIn, timeInOrTimeOut, timeOutOrUndef, isRestDayArg) {
    let date, timeIn, timeOut, isRestDay;
    if (isRestDayArg !== undefined) {
        // called as (date, timeIn, timeOut, isRestDay)
        date      = empIdOrDate;
        timeIn    = dateOrTimeIn;
        timeOut   = timeInOrTimeOut;
        isRestDay = timeOutOrUndef === true;
    } else if (timeOutOrUndef !== undefined) {
        // legacy 4-arg: (empId, date, timeIn, timeOut)
        date      = dateOrTimeIn;
        timeIn    = timeInOrTimeOut;
        timeOut   = timeOutOrUndef;
        isRestDay = false;
    } else {
        // 3-arg: (date, timeIn, timeOut)
        date      = empIdOrDate;
        timeIn    = dateOrTimeIn;
        timeOut   = timeInOrTimeOut;
        isRestDay = false;
    }

    document.getElementById('schedModalTitle').textContent  = 'Edit Schedule';
    document.getElementById('schedSubmitLabel').textContent = 'Update Schedule';
    document.getElementById('isEditMode').value             = '1';
    document.getElementById('modalIsRestDay').value         = isRestDay ? '1' : '0';
    document.getElementById('modalRestDayCheck').checked    = isRestDay;
    document.getElementById('modalTimeIn').value            = timeIn;
    document.getElementById('modalTimeOut').value           = timeOut;
    document.getElementById('modalTimeFields').style.display = isRestDay ? 'none' : '';
    document.getElementById('modalTimeIn').required          = !isRestDay;
    document.getElementById('modalTimeOut').required         = !isRestDay;
    selectedDates = [date];
    renderDateTags();
    if (fp) fp.setDate([date, date], false);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('schedModal')).show();
}

// ---- Rest day modal helpers ----
function openRestDayModal() {
    openManageModal();
}

// Shim for get_admin_schedule_calendar.php
function deleteScheduleDay(empId, date) { deleteSchedule(date); }
function openEditAttModal() { /* not available on this page */ }

function closeSchedModal() {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('schedModal')).hide();
}

function prepareSubmit() {
    document.getElementById('selectedDatesInput').value = JSON.stringify(selectedDates);
    if (selectedDates.length === 0) {
        showToast('Please select at least one date.', 'warning');
        return false;
    }
    const isEdit = document.getElementById('isEditMode').value === '1';
    if (!isEdit) {
        const conflicts = selectedDates.filter(d => scheduledDates.has(d));
        if (conflicts.length > 0) {
            const msg = conflicts.length === 1
                ? `A schedule for ${conflicts[0]} already exists. Replace it?`
                : `Schedules for ${conflicts.length} selected dates already exist. Replace them?`;
            if (!confirm(msg)) return false;
        }
    }
    return true;
}

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
    document.getElementById('ale-error').style.display      = 'none';
    document.getElementById('ale-success').style.display    = 'none';
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
                fetchAdminLogs();
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

function deleteSchedule(date) {
    if (!confirm('Delete schedule for ' + date + '?')) return;
    fetch(`admin_employee_view.php?employee_id=${EMP_URL_ID}&ajax_delete=1&emp=${EMP_ID}&date=${date}`)
        .then(() => { if (adminCalendar) adminCalendar.refetchEvents(); });
}

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
