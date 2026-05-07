<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';
require_once '../system_functions/system_service.php';
require_once '../system_functions/system_library.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_GET['id'])) {
    header("Location: admin_employees_list.php");
    exit();
}

$employeeId = intval($_GET['id']);

// ---- HANDLE EMPLOYEE EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_employee') {

    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $role       = $_POST['role'] ?? 'employee';
    $department = !empty($_POST['department_id']) ? $_POST['department_id'] : null;

    /* --------------------------------------------
       DUPLICATE EMAIL CHECK
       Excludes current employee ID
    -------------------------------------------- */

    $dup = $pdo->prepare("
        SELECT id
        FROM employees
        WHERE email = ?
        AND id != ?
        LIMIT 1
    ");

    $dup->execute([$email, $employeeId]);

    if ($dup->fetch()) {
        header("Location: admin_employee_view.php?id=$employeeId&edit_error=duplicate_email");
        exit();
    }

    /* --------------------------------------------
       UPDATE EMPLOYEE
    -------------------------------------------- */

    if (!empty($password)) {

        $pdo->prepare("
            UPDATE employees
            SET
                name = ?,
                email = ?,
                password = ?,
                role = ?,
                department_id = ?
            WHERE id = ?
        ")->execute([
            $name,
            $email,
            $password,
            $role,
            $department,
            $employeeId
        ]);

    } else {

        $pdo->prepare("
            UPDATE employees
            SET
                name = ?,
                email = ?,
                role = ?,
                department_id = ?
            WHERE id = ?
        ")->execute([
            $name,
            $email,
            $role,
            $department,
            $employeeId
        ]);
    }

    header("Location: admin_employee_view.php?id=$employeeId");
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
    $is_overnight = $time_out < $time_in;

    if (!empty($dates) && $postEmpId) {
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

            $existsStmt->execute([$postEmpId, $date]);
            if ($existsStmt->fetch()) {
                $updateStmt->execute([$startDT, $endDT, $postEmpId, $date]);
                $updateAttendance->execute([$startDT, $endDT, $postEmpId, $date]);
            } else {
                $insertSchedule->execute([$postEmpId, $date, $startDT, $endDT]);
                $schedId = $pdo->lastInsertId() ?: null;
                $insertAttendance->execute([$postEmpId, $schedId, $date, $startDT, $endDT]);
            }
        }
    }

    header("Location: admin_employee_view.php?id=$employeeId");
    exit();
}
// ---- HANDLE EMPLOYEE DELETE ----
if (isset($_GET['action']) && $_GET['action'] === 'delete_employee') {

    // delete related records first
    $pdo->prepare("DELETE FROM logs WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM attendances WHERE employee_id = ?")->execute([$employeeId]);
    $pdo->prepare("DELETE FROM schedules WHERE employee_id = ?")->execute([$employeeId]);

    // delete employee
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$employeeId]);

    header("Location: admin_manage_employees.php");
    exit();
}


// ---- GET EMPLOYEE ----
$stmt = $pdo->prepare("
    SELECT e.*, d.department_name, d.department_code
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

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

// ---- SCHEDULES (calendar) ----
$schedStmt = $pdo->prepare("
    SELECT schedule_date, scheduled_start, scheduled_end, is_rest_day, status
    FROM schedules
    WHERE employee_id = ? AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date ASC
");
$schedStmt->execute([$employeeId, $monthStart, $monthEnd]);
$schedulesByDate = [];
foreach ($schedStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $schedulesByDate[$s['schedule_date']] = $s;
}

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

    <!-- Global CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../navbars_revised.css">

    <!-- Page component CSS (calendar + gantt styles) -->
    <link rel="stylesheet" href="admin_employee_view.css">
</head>
<body>

<?php $currentPage = 'employees'; include '../sidebar_revised.php'; ?>

<div id="main-wrapper">

    <?php include '../topbar_revised.php'; $employeeId = $emp['id']; ?>

    <!-- Employee header strip -->
    <div class="card card-glass employee-view-card">
        <div class="card-body employee-view-card-body">
            <div class="ev-header-row">

                <!-- Back button -->
                <a href="admin_manage_employees.php" class="btn btn-sm btn-outline-light ev-back-btn">
                    <i class="bi bi-arrow-left"></i> Back
                </a>

                <!-- Avatar + info -->
                <div class="ev-emp-info">
                    <i class="bi bi-person-circle ev-avatar"></i>
                    <div class="ev-emp-details">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="ev-emp-name"><?= htmlspecialchars($emp['name']) ?></span>
                            <span class="empRoleBadge empRole-<?= htmlspecialchars($emp['role']) ?>">
                                <?= ucfirst($emp['role']) ?>
                            </span>
                        </div>
                        <div class="ev-emp-metas">
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

                <!-- Actions -->
                <div class="ev-actions">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#edit-employee-modal" class="btn btn-info">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>

            </div>
        </div>
    </div>

    <?php if (($_GET['edit_error'] ?? '') === 'duplicate_email'): ?>
        <div class="alert alert-danger alert-dismissible fade show mx-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            That email is already in use by another employee.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="ev-tabs card-glass">
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

            </ul>
        </div>

        <div class="card-body">
            <div class="tab-content">

                <div class="tab-pane fade show active" id="tab1" role="tabpanel">

                    <!-- Month nav + Add button -->
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
                            <span id="chip-sched-count" class="tab-summary-chip" style="color:var(--text-muted);">
                                <?= count($schedulesByDate) ?> scheduled day<?= count($schedulesByDate) !== 1 ? 's' : '' ?>
                            </span>
                            <button class="btn btn-success sched-add-btn" onclick="openAddModal()"
                                data-bs-toggle="modal" data-bs-target="#schedModal">
                                <i class="bi bi-plus-lg"></i> Add Schedule
                            </button>
                        </div>
                    </div>

                    <!-- Calendar grid -->
                    <div class="sched-cal-container">
                        <div class="sched-cal-grid">

                            <!-- Weekday headers -->
                            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $h): ?>
                                <div class="sched-cal-day-header"><?= $h ?></div>
                            <?php endforeach; ?>

                            <!-- Leading empty cells -->
                            <?php
                            $firstDow    = (int) date('w', strtotime($monthStart));
                            $daysInMonth = (int) date('t', strtotime($monthStart));
                            for ($i = 0; $i < $firstDow; $i++): ?>
                                <div class="sched-cal-day empty"></div>
                            <?php endfor; ?>

                            <!-- Day cells -->
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $ds    = sprintf('%04d-%02d-%02d', $viewYear, $viewMonthNum, $d);
                                $sched = $schedulesByDate[$ds] ?? null;
                                $isToday = $ds === $todayStr;
                                $isPast  = $ds < $todayStr;
                                $cls = 'sched-cal-day';
                                if ($isToday) $cls .= ' is-today';
                                elseif ($isPast) $cls .= ' is-past';
                                if ($sched) {
                                    $cls .= ' has-sched';
                                    $st = $sched['status'] ?? 'approved';
                                    if ($st === 'pending')  $cls .= ' sched-pending';
                                    if ($st === 'rejected') $cls .= ' sched-rejected';
                                }
                            ?>
                            <div class="<?= $cls ?>">
                                <div class="sched-cal-day-num <?= $isToday ? 'is-today-num' : '' ?>">
                                    <?= $d ?>
                                </div>

                                <?php if ($sched):
                                    $startTs = strtotime($sched['scheduled_start']);
                                    $endTs   = strtotime($sched['scheduled_end']);
                                    $hour    = (int) date('H', $startTs);
                                    $isNight = $hour >= 18 || $hour < 6;
                                    $tIn     = date('g:i A', $startTs);
                                    $tOut    = date('g:i A', $endTs);
                                    $tInVal  = date('H:i', $startTs);
                                    $tOutVal = date('H:i', $endTs);
                                    $status  = $sched['status'] ?? 'approved';
                                ?>
                                    <span class="sched-cal-shift-badge <?= $isNight ? 'night' : 'day' ?>">
                                        <?= $isNight ? 'Night' : 'Day' ?>
                                    </span>
                                    <div class="sched-cal-times"><?= $tIn ?><br><?= $tOut ?></div>
                                    <span class="sched-cal-status-badge sched-status-<?= htmlspecialchars($status) ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                    <div class="sched-cal-day-actions">
                                        <button class="sched-cal-action-btn edit" title="Edit"
                                            onclick='openEditModal(<?= json_encode($ds) ?>, <?= json_encode($tInVal) ?>, <?= json_encode($tOutVal) ?>); event.stopPropagation();'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="sched-cal-action-btn delete" title="Delete"
                                            onclick='deleteSchedule(<?= json_encode($ds) ?>); event.stopPropagation();'>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>

                        </div><!-- .sched-cal-grid -->
                    </div><!-- .sched-cal-container -->

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

                    <!-- Month nav + count -->
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
                        <span id="chip-logs-count" class="tab-summary-chip" style="color:var(--text-muted);">
                            <?= count($tapLogs) ?> log<?= count($tapLogs) !== 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <!-- Logs table -->
                    <div class="logs-table-wrapper">
                        <table class="table table-borderless table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="position:sticky;top:0;background:var(--bg-dark);color:var(--text-muted);z-index:1;border-bottom:1px solid var(--glass-border);font-size:0.78rem;font-weight:400;">Date &amp; Time</th>
                                    <th style="position:sticky;top:0;background:var(--bg-dark);color:var(--text-muted);z-index:1;border-bottom:1px solid var(--glass-border);font-size:0.78rem;font-weight:400;">Type</th>
                                    <th style="position:sticky;top:0;background:var(--bg-dark);color:var(--text-muted);z-index:1;border-bottom:1px solid var(--glass-border);font-size:0.78rem;font-weight:400;">Within Office</th>
                                    <th style="position:sticky;top:0;background:var(--bg-dark);color:var(--text-muted);z-index:1;border-bottom:1px solid var(--glass-border);font-size:0.78rem;font-weight:400;">Distance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tapLogs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5"
                                            style="color:var(--text-muted); background:rgba(0,0,0,0.2);">
                                            <i class="bi bi-clock-history"
                                                style="font-size:1.8rem; display:block; margin-bottom:0.4rem; opacity:0.4;"></i>
                                            No logs found for <?= htmlspecialchars($monthLabel) ?>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $typeMap = [
                                        'IN'        => ['label' => 'Time In',   'cls' => 'in'],
                                        'OUT'       => ['label' => 'Time Out',  'cls' => 'out'],
                                        'BREAK_IN'  => ['label' => 'Break In',  'cls' => 'break-in'],
                                        'BREAK_OUT' => ['label' => 'Break Out', 'cls' => 'break-out'],
                                    ];
                                    foreach ($tapLogs as $log):
                                        $typeInfo = $typeMap[$log['log_type']] ?? ['label' => $log['log_type'], 'cls' => ''];
                                    ?>
                                    <tr>
                                        <td style="background:rgba(0,0,0,0.2); color:var(--text-light); border-color:var(--glass-border); vertical-align:middle;">
                                            <div style="font-size:0.85rem;"><?= date('D, M j, Y', strtotime($log['log_time'])) ?></div>
                                            <div style="font-size:0.73rem; color:var(--text-muted);"><?= date('g:i:s A', strtotime($log['log_time'])) ?></div>
                                        </td>
                                        <td style="background:rgba(0,0,0,0.2); border-color:var(--glass-border); vertical-align:middle;">
                                            <span class="log-type-badge <?= $typeInfo['cls'] ?>">
                                                <i class="bi bi-circle-fill" style="font-size:0.45rem;"></i>
                                                <?= $typeInfo['label'] ?>
                                            </span>
                                        </td>
                                        <td style="background:rgba(0,0,0,0.2); border-color:var(--glass-border); vertical-align:middle;">
                                            <?php if ($log['is_within_office']): ?>
                                                <span style="color:var(--primary-color); font-size:0.82rem;">
                                                    <i class="bi bi-check-circle-fill"></i> Yes
                                                </span>
                                            <?php else: ?>
                                                <span style="color:var(--danger-color); font-size:0.82rem;">
                                                    <i class="bi bi-x-circle-fill"></i> No
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="background:rgba(0,0,0,0.2); color:var(--text-muted); border-color:var(--glass-border); vertical-align:middle; font-size:0.82rem;">
                                            <?= $log['distance_meters'] !== null
                                                ? number_format((float) $log['distance_meters'], 0) . ' m'
                                                : '—' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div><!-- .logs-table-wrapper -->

                </div>

            </div>
        </div>
    </div>

</div><!-- #main-wrapper -->

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
        <span class="ganttToolTipLabel">Overtime</span>
        <span class="ganttToolTipValue" id="gt-ot"></span>
    </div>
    <div class="ganttToolTipRow ganttToolTipUnderTime" id="gt-ut-row">
        <span class="ganttToolTipLabel">Undertime</span>
        <span class="ganttToolTipValue" id="gt-ut"></span>
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

            <form method="POST" action="admin_employee_view.php?id=<?= $employeeId ?>" id="schedForm">
                <input type="hidden" name="action" value="save_schedule">
                <div class="modal-body">

                    <input type="hidden" name="employee_id" id="modalEmpId" value="<?= $employeeId ?>">
                    <input type="hidden" name="selected_dates" id="selectedDatesInput">
                    <input type="hidden" name="is_edit" id="isEditMode" value="0">

                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <input type="text" id="modalEmpName"
                               class="form-control"
                               value="<?= htmlspecialchars($emp['name']) ?>"
                               readonly>
                    </div>

                    <div class="row g-3">
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
                <h5 class="modal-title">Editing <?= htmlspecialchars($emp['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="admin_employee_view.php?id=<?= $employeeId ?>">

                <input type="hidden" name="action" value="edit_employee">

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= htmlspecialchars($emp['name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($emp['email']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">New Password <small class="text-muted">(leave blank to keep)</small></label>
                            <input type="password" name="password" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="employee"  <?= $emp['role'] === 'employee'  ? 'selected' : '' ?>>Employee</option>
                                <option value="workforce" <?= $emp['role'] === 'workforce' ? 'selected' : '' ?>>Workforce</option>
                                <option value="admin"     <?= $emp['role'] === 'admin'     ? 'selected' : '' ?>>Admin</option>
                            </select>
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
                Are you sure you want to delete <strong><?= htmlspecialchars($emp['name']) ?></strong>?
                This will permanently remove their schedules and logs.
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="admin_employee_view.php?id=<?= $employeeId ?>&action=delete_employee" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Delete
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

// ---- State ----
let selectedDates = [];
let fp            = null;
const EMP_ID      = <?= $employeeId ?>;
let currentMonth  = '<?= $rawMonth ?>';
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
function loadingHTML() {
    return '<div class="text-center py-5" style="color:var(--text-muted);"><i class="bi bi-arrow-clockwise" style="font-size:1.5rem;"></i></div>';
}

// ---- Month navigation ----
function navigateMonth(ym) {
    currentMonth = ym;

    document.querySelectorAll('.sched-month-label').forEach(el => el.textContent = monthLabel(ym));
    history.pushState({ month: ym }, '', `?id=${EMP_ID}&month=${ym}`);

    const activeBtn = document.querySelector('#myTab .nav-link.active');
    const activeTab = activeBtn ? activeBtn.dataset.bsTarget : '#tab1';
    loadTab(activeTab, ym);

    Object.keys(tabLoadedMonth).forEach(k => { tabLoadedMonth[k] = k === activeTab ? ym : null; });
}
function navigatePrev() {
    const { year, month } = parseYM(currentMonth);
    const d = new Date(year, month - 2, 1);
    navigateMonth(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
}
function navigateNext() {
    const { year, month } = parseYM(currentMonth);
    const d = new Date(year, month, 1);
    navigateMonth(`${d.getFullYear()}-${pad(d.getMonth() + 1)}`);
}

// ---- Load tab by target ----
function loadTab(tabTarget, ym) {
    const { year, month }        = parseYM(ym);
    const { startDate, endDate } = monthDates(ym);
    if      (tabTarget === '#tab1') loadCalendar(year, month);
    else if (tabTarget === '#tab2') loadRecords(startDate, endDate);
    else if (tabTarget === '#tab3') loadLogs(startDate, endDate);
}

// ---- Tab 1: Calendar ----
function loadCalendar(year, month) {
    const container = document.querySelector('.sched-cal-container');
    container.innerHTML = loadingHTML();
    fetch(`get_admin_employee_calendar.php?employee_id=${EMP_ID}&year=${year}&month=${month}`)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
            const count = container.querySelectorAll('.sched-cal-day.has-sched').length;
            const chip  = document.getElementById('chip-sched-count');
            if (chip) chip.textContent = count + ' scheduled day' + (count !== 1 ? 's' : '');
        })
        .catch(() => {
            container.innerHTML = '<div class="text-center py-4" style="color:var(--danger-color);">Failed to load calendar.</div>';
        });
}

// ---- Tab 2: Records / Gantt ----
function loadRecords(startDate, endDate) {
    const container = document.querySelector('.ganttContainer');
    container.innerHTML = loadingHTML();
    fetch(`get_admin_employee_records.php?employee_id=${EMP_ID}&start=${startDate}&end=${endDate}`)
        .then(r => r.text())
        .then(html => {
            const match = html.match(/<!--SUMMARY:({.*?})-->/);
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

// ---- Tab 3: Logs ----
function loadLogs(startDate, endDate) {
    const tbody = document.querySelector('#tab3 .logs-table-wrapper tbody');
    tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4" style="color:var(--text-muted);background:rgba(0,0,0,0.2);">${loadingHTML()}</td></tr>`;

    fetch(`get_employee_logs.php?employee_id=${EMP_ID}&date_from=${startDate}&date_to=${endDate}`)
        .then(r => r.json())
        .then(logs => {
            const chip = document.getElementById('chip-logs-count');
            if (chip) chip.textContent = logs.length + ' log' + (logs.length !== 1 ? 's' : '');

            if (logs.length === 0) {
                const lbl = document.querySelector('#tab3 .sched-month-label')?.textContent ?? '';
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-5" style="color:var(--text-muted);background:rgba(0,0,0,0.2);">
                    <i class="bi bi-clock-history" style="font-size:1.8rem;display:block;margin-bottom:0.4rem;opacity:0.4;"></i>
                    No logs found for ${lbl}.
                </td></tr>`;
                return;
            }

            const typeMap = {
                IN:        ['Time In',   'in'],
                OUT:       ['Time Out',  'out'],
                BREAK_IN:  ['Break In',  'break-in'],
                BREAK_OUT: ['Break Out', 'break-out'],
            };

            tbody.innerHTML = logs.map(log => {
                const [label, cls] = typeMap[log.log_type] ?? [log.log_type, ''];
                const dt     = new Date(log.log_time.replace(' ', 'T'));
                const dtStr  = dt.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });
                const tmStr  = dt.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', second:'2-digit', hour12:true });
                const dist   = log.distance_meters != null
                    ? Number(log.distance_meters).toLocaleString('en-US', { maximumFractionDigits: 0 }) + ' m'
                    : '—';
                const office = log.is_within_office
                    ? `<span style="color:var(--primary-color);font-size:0.82rem;"><i class="bi bi-check-circle-fill"></i> Yes</span>`
                    : `<span style="color:var(--danger-color);font-size:0.82rem;"><i class="bi bi-x-circle-fill"></i> No</span>`;
                return `<tr>
                    <td style="background:rgba(0,0,0,0.2);color:var(--text-light);border-color:var(--glass-border);vertical-align:middle;">
                        <div style="font-size:0.85rem;">${dtStr}</div>
                        <div style="font-size:0.73rem;color:var(--text-muted);">${tmStr}</div>
                    </td>
                    <td style="background:rgba(0,0,0,0.2);border-color:var(--glass-border);vertical-align:middle;">
                        <span class="log-type-badge ${cls}"><i class="bi bi-circle-fill" style="font-size:0.45rem;"></i> ${label}</span>
                    </td>
                    <td style="background:rgba(0,0,0,0.2);border-color:var(--glass-border);vertical-align:middle;">${office}</td>
                    <td style="background:rgba(0,0,0,0.2);color:var(--text-muted);border-color:var(--glass-border);vertical-align:middle;font-size:0.82rem;">${dist}</td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4" style="color:var(--danger-color);">Failed to load logs.</td></tr>`;
        });
}

// ---- DOMContentLoaded ----
document.addEventListener('DOMContentLoaded', () => {
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

    initGanttCursors();

    // Load whichever tab is currently active via AJAX on first paint,
    // so server-rendered content (which lacks leave/OB awareness) is replaced immediately
    const activeBtn = document.querySelector('#myTab .nav-link.active');
    const activeTab = activeBtn ? activeBtn.dataset.bsTarget : '#tab1';
    loadTab(activeTab, currentMonth);
    tabLoadedMonth[activeTab] = currentMonth;

    fp = flatpickr('#schedDatePicker', {
        mode: 'multiple',
        dateFormat: 'Y-m-d',
        onChange(dates) {
            selectedDates = dates.map(d => {
                const y   = d.getFullYear();
                const m   = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            });
            renderDateTags();
        }
    });

    // Schedule form: AJAX submit → reload only the calendar
    document.getElementById('schedForm').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!prepareSubmit()) return;
        fetch(this.getAttribute('action'), { method: 'POST', body: new FormData(this) })
            .then(() => {
                closeSchedModal();
                const { year, month } = parseYM(currentMonth);
                loadCalendar(year, month);
                tabLoadedMonth['#tab1'] = currentMonth;
            })
            .catch(() => alert('Failed to save schedule. Please try again.'));
    });

    // Browser back / forward
    window.addEventListener('popstate', e => {
        if (e.state && e.state.month) {
            currentMonth = e.state.month;
            document.querySelectorAll('.sched-month-label').forEach(el => el.textContent = monthLabel(currentMonth));
            const activeBtn = document.querySelector('#myTab .nav-link.active');
            const activeTab = activeBtn ? activeBtn.dataset.bsTarget : '#tab1';
            loadTab(activeTab, currentMonth);
        }
    });

    history.replaceState({ month: currentMonth }, '', `?id=${EMP_ID}&month=${currentMonth}`);
});

// ---- Schedule modal helpers ----
function renderDateTags() {
    document.getElementById('selectedDatesList').innerHTML = selectedDates
        .map(d => `<span class="selected-date-tag">${d}
            <span class="selected-date-remove" onclick="removeDate('${d}')">&times;</span>
        </span>`)
        .join('');
}

function removeDate(d) {
    selectedDates = selectedDates.filter(x => x !== d);
    if (fp) fp.setDate(selectedDates, false);
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

// Accepts both (date, timeIn, timeOut) and (empId, date, timeIn, timeOut) — empId ignored
function openEditModal(empIdOrDate, dateOrTimeIn, timeInOrTimeOut, timeOutOrUndef) {
    const date    = timeOutOrUndef !== undefined ? dateOrTimeIn    : empIdOrDate;
    const timeIn  = timeOutOrUndef !== undefined ? timeInOrTimeOut : dateOrTimeIn;
    const timeOut = timeOutOrUndef !== undefined ? timeOutOrUndef  : timeInOrTimeOut;

    document.getElementById('schedModalTitle').textContent  = 'Edit Schedule';
    document.getElementById('schedSubmitLabel').textContent = 'Update Schedule';
    document.getElementById('isEditMode').value             = '1';
    document.getElementById('modalTimeIn').value            = timeIn;
    document.getElementById('modalTimeOut').value           = timeOut;
    selectedDates = [date];
    renderDateTags();
    if (fp) fp.setDate([date], false);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('schedModal')).show();
}

// Shim for get_admin_schedule_calendar.php
function deleteScheduleDay(empId, date) { deleteSchedule(date); }
function openEditAttModal() { /* not available on this page */ }

function closeSchedModal() {
    bootstrap.Modal.getInstance(document.getElementById('schedModal'))?.hide();
}

function prepareSubmit() {
    document.getElementById('selectedDatesInput').value = JSON.stringify(selectedDates);
    if (selectedDates.length === 0) {
        alert('Please select at least one date.');
        return false;
    }
    const isEdit = document.getElementById('isEditMode').value === '1';
    if (!isEdit) {
        const existing = new Set(
            [...document.querySelectorAll('.sched-cal-day.has-sched[data-date]')]
                .map(el => el.dataset.date)
        );
        const conflicts = selectedDates.filter(d => existing.has(d));
        if (conflicts.length > 0) {
            const msg = conflicts.length === 1
                ? `A schedule for ${conflicts[0]} already exists. Replace it?`
                : `Schedules for ${conflicts.length} selected dates already exist. Replace them?`;
            if (!confirm(msg)) return false;
        }
    }
    return true;
}

function deleteSchedule(date) {
    if (!confirm('Delete schedule for ' + date + '?')) return;
    fetch(`admin_employee_view.php?id=${EMP_ID}&ajax_delete=1&emp=${EMP_ID}&date=${date}`)
        .then(() => {
            const { year, month } = parseYM(currentMonth);
            loadCalendar(year, month);
        });
}
</script>

</body>
</html>