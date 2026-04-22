<?php
ob_clean();
session_start();
require_once 'db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$employeeId = $_SESSION['user_id'];

$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');
$today      = date('Y-m-d');

// Get schedule
$stmt = $pdo->prepare("
    SELECT time_in, time_out, is_rest_day
    FROM schedules
    WHERE employee_id = ?
    AND work_date = ?
");
$stmt->execute([$employeeId, $today]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

// Get last log today
$stmt = $pdo->prepare("
    SELECT log_type
    FROM logs
    WHERE employee_id = ?
    AND log_time BETWEEN ? AND ?
    ORDER BY log_time DESC
    LIMIT 1
");
$stmt->execute([$employeeId, $todayStart, $todayEnd]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's attendance row
$stmt = $pdo->prepare("
    SELECT actual_time_in, actual_time_out, scheduled_time_out, status
    FROM attendance
    WHERE employee_id = ?
    AND date = ?
");
$stmt->execute([$employeeId, $today]);
$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Single source of truth: last log determines timed-in state
$isTimedIn = ($lastLog['log_type'] ?? null) === 'login';

// Timein functions
if (!$isTimedIn) {
    $schedTimeIn  = $schedule['time_in']  ?? '00:00:00';
    $schedTimeOut = $schedule['time_out'] ?? '00:00:00';

    if (!$schedule) {
        $schedTimeIn  = '00:00:00';
        $schedTimeOut = '00:00:00';
    }

    $alreadyTimedInBefore = !empty($todayAttendance['actual_time_in']);

    // Only enforce schedule window on the FIRST time-in
    if (!$alreadyTimedInBefore) {
        $allowedStart = strtotime($today . ' ' . $schedTimeIn);
        $allowedEnd   = strtotime($today . ' ' . $schedTimeOut);

        if (time() > $allowedEnd) {
            echo json_encode([
                'status'  => 'absent',
                'message' => 'Shift already ended. Marked as absent.'
            ]);
            exit();
        }
    }

    // Log the time in
    $pdo->prepare("
        INSERT INTO logs (employee_id, log_type, log_time)
        VALUES (?, 'login', NOW())
    ")->execute([$employeeId]);

    // Calculate late minutes (only on first time-in)
    $lateMinutes = 0;
    if (empty($todayAttendance['actual_time_in']) && $schedTimeIn !== '00:00:00') {
        $schedTs = strtotime($today . ' ' . $schedTimeIn);
        if (time() > $schedTs) {
            $lateMinutes = (int) floor((time() - $schedTs) / 60);
        }
    }

    $status = $lateMinutes > 0 ? 'late' : 'present';

    // Insert attendance row on first time-in; ignore subsequent time-ins
    $pdo->prepare("
        INSERT INTO attendance (
            employee_id,
            date,
            scheduled_time_in,
            scheduled_time_out,
            actual_time_in,
            late_minutes,
            status
        )
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE
            actual_time_in = COALESCE(actual_time_in, VALUES(actual_time_in)),
            late_minutes   = IF(actual_time_in IS NULL, VALUES(late_minutes), late_minutes),
            status         = IF(actual_time_in IS NULL, VALUES(status), status)
    ")->execute([
        $employeeId,
        $today,
        $schedTimeIn,
        $schedTimeOut,
        $lateMinutes,
        $status
    ]);

    echo json_encode(['status' => 'timed_in']);
    exit();
}

// Timeout functions

// Log the time out
$pdo->prepare("
    INSERT INTO logs (employee_id, log_type, log_time)
    VALUES (?, 'logout', NOW())
")->execute([$employeeId]);

if (!$todayAttendance || empty($todayAttendance['actual_time_in'])) {
    echo json_encode(['status' => 'no_attendance']);
    exit();
}

// Recalculate total work hours from ALL login/logout pairs today
$stmt = $pdo->prepare("
    SELECT log_type, log_time
    FROM logs
    WHERE employee_id = ?
    AND log_time BETWEEN ? AND ?
    ORDER BY log_time ASC
");
$stmt->execute([$employeeId, $todayStart, $todayEnd]);
$allLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSeconds  = 0;
$lastLoginTime = null;

foreach ($allLogs as $log) {
    if ($log['log_type'] === 'login') {
        $lastLoginTime = strtotime($log['log_time']);
    } elseif ($log['log_type'] === 'logout' && $lastLoginTime !== null) {
        $totalSeconds += strtotime($log['log_time']) - $lastLoginTime;
        $lastLoginTime = null;
    }
}

// Deduct 1-hour break once per day, only if worked more than 1 hour
if ($totalSeconds > 3600) {
    $totalSeconds -= 3600;
}
$hoursWorked = round($totalSeconds / 3600, 2);

// Undertime / Overtime
$undertimeMinutes = 0;
$overtimeMinutes  = 0;
$schedOut = $todayAttendance['scheduled_time_out'] ?? '00:00:00';

if ($schedOut && $schedOut !== '00:00:00') {
    $schedOutTs = strtotime($today . ' ' . $schedOut);
    $nowTs      = time();

    if ($nowTs < $schedOutTs) {
        $undertimeMinutes = (int) floor(($schedOutTs - $nowTs) / 60);
    } else {
        $overtimeMinutes = (int) floor(($nowTs - $schedOutTs) / 60);
    }
}

// Preserve 'late' status if set on first time-in
$finalStatus   = $undertimeMinutes > 0 ? 'incomplete' : 'present';
$currentStatus = $todayAttendance['status'] ?? '';
if ($currentStatus === 'late') {
    $finalStatus = 'late';
}

// Always overwrite actual_time_out with the latest logout
$pdo->prepare("
    UPDATE attendance
    SET
        actual_time_out   = NOW(),
        total_work_hours  = ?,
        undertime_minutes = ?,
        overtime_minutes  = ?,
        overtime_status   = IF(? > 0, 'pending', 'none'),
        status            = ?
    WHERE employee_id = ?
    AND date = ?
")->execute([
    $hoursWorked,
    $undertimeMinutes,
    $overtimeMinutes,
    $overtimeMinutes,
    $finalStatus,
    $employeeId,
    $today
]);

echo json_encode(['status' => 'timed_out']);
exit();