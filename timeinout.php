<?php
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

/* ================================================
   GET SCHEDULE
================================================ */
$stmt = $pdo->prepare("
    SELECT time_in, time_out, is_rest_day
    FROM schedules
    WHERE employee_id = ?
    AND work_date = ?
");
$stmt->execute([$employeeId, $today]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================================================
   GET LAST LOG — scoped to TODAY only
   (prevents clock changes from bleeding in old logs)
================================================ */
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

/* ================================================
   CROSS-CHECK ATTENDANCE TABLE
   If attendance has actual_time_in but no actual_time_out,
   the employee is considered timed in — regardless of logs.
================================================ */
$stmt = $pdo->prepare("
    SELECT actual_time_in, actual_time_out
    FROM attendance
    WHERE employee_id = ?
    AND date = ?
");
$stmt->execute([$employeeId, $today]);
$todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

$isTimedIn = ($lastLog && $lastLog['log_type'] === 'login')
          || (
                $todayAttendance
                && !empty($todayAttendance['actual_time_in'])
                && empty($todayAttendance['actual_time_out'])
             );


/* ================================================
   TOGGLE
================================================ */
if (!$isTimedIn) {

    /* ================= TIME IN ================= */

    $pdo->prepare("
        INSERT INTO logs (employee_id, log_type)
        VALUES (?, 'login')
    ")->execute([$employeeId]);

    $schedTimeIn  = $schedule['time_in']  ?? '00:00:00';
    $schedTimeOut = $schedule['time_out'] ?? '00:00:00';

    $lateMinutes = 0;
    if ($schedTimeIn && $schedTimeIn !== '00:00:00') {
        $schedTs = strtotime($today . ' ' . $schedTimeIn);
        $nowTs   = time();

        // Late Computation
        if ($nowTs > $schedTs) {
            $lateMinutes = (int) floor(($nowTs - $schedTs) / 60);
        }
    }

    $status = $lateMinutes > 0 ? 'late' : 'present';

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


/* ================================================
   TIME OUT
================================================ */

$pdo->prepare("
    INSERT INTO logs (employee_id, log_type)
    VALUES (?, 'logout')
")->execute([$employeeId]);

$stmt = $pdo->prepare("
    SELECT actual_time_in, scheduled_time_out
    FROM attendance
    WHERE employee_id = ?
    AND date = ?
    LIMIT 1
");
$stmt->execute([$employeeId, $today]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance || empty($attendance['actual_time_in'])) {
    echo json_encode(['status' => 'no_attendance']);
    exit();
}

$timeIn  = strtotime($attendance['actual_time_in']);
$timeOut = time();

$secondsWorked = max(0, $timeOut - $timeIn - 3600);
$hoursWorked   = round($secondsWorked / 3600, 2);

/* =========================
   UNDERTIME / OVERTIME
========================= */
$undertimeMinutes = 0;
$overtimeMinutes  = 0;

$schedOut = $attendance['scheduled_time_out'];

if ($schedOut && $schedOut !== '00:00:00') {
    $schedOutTs = strtotime($today . ' ' . $schedOut);

    // Undertime computation
    if ($timeOut < $schedOutTs) {
        $undertimeMinutes = (int) floor(($schedOutTs - $timeOut) / 60);
    } elseif ($timeOut > $schedOutTs) {
        //Overtime computation
        $overtimeMinutes = (int) floor(($timeOut - $schedOutTs) / 60);
    }
}

/* =========================
   STATUS
========================= */
$finalStatus = $undertimeMinutes > 0 ? 'incomplete' : 'present';

$stmt = $pdo->prepare("
    SELECT status FROM attendance
    WHERE employee_id = ?
    AND date = ?
");
$stmt->execute([$employeeId, $today]);
$currentStatus = $stmt->fetchColumn();

if ($currentStatus === 'late') {
    $finalStatus = 'late';
}

/* =========================
   UPDATE ATTENDANCE
========================= */
$stmt = $pdo->prepare("
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
");

$stmt->execute([
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