<?php
session_start();
require_once 'db.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    exit();
}

$employeeId = $_SESSION['user_id'];

// Today's range
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

// Check today's logs
$stmt = $pdo->prepare("
    SELECT 
        SUM(log_type = 'login') as total_login,
        SUM(log_type = 'logout') as total_logout
    FROM logs
    WHERE employee_id = ?
    AND log_time BETWEEN ? AND ?
");
$stmt->execute([$employeeId, $todayStart, $todayEnd]);
$todayLogs = $stmt->fetch(PDO::FETCH_ASSOC);

$hasLoginToday = $todayLogs['total_login'] > 0;
$hasLogoutToday = $todayLogs['total_logout'] > 0;

// Get last log
$stmt = $pdo->prepare("
    SELECT log_type 
    FROM logs 
    WHERE employee_id = ?
    ORDER BY log_time DESC 
    LIMIT 1
");
$stmt->execute([$employeeId]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

$isTimedIn = ($lastLog && $lastLog['log_type'] === 'login');

// TOGGLE
if (!$isTimedIn) {

    // TIME IN
    $stmt = $pdo->prepare("
        INSERT INTO logs (employee_id, log_type)
        VALUES (?, 'login')
    ");
    $stmt->execute([$employeeId]);

    // FIRST TIME IN TODAY → CREATE attendance
    if (!$hasLoginToday) {
        $stmt = $pdo->prepare("
            INSERT INTO attendance (employee_id, date, time_in, status)
            VALUES (?, CURDATE(), NOW(), 'in_progress')
        ");
        $stmt->execute([$employeeId]);
    }
    // Computation tardiness
    // Compare schedule supposed time in
    $status = "timed_in";

} else {

    // TIME OUT
    $stmt = $pdo->prepare("
        INSERT INTO logs (employee_id, log_type)
        VALUES (?, 'logout')
    ");
    $stmt->execute([$employeeId]);

    // ALWAYS update attendance on logout (not just first time)
    $stmt = $pdo->prepare("
        SELECT time_in 
        FROM attendance
        WHERE employee_id = ?
        AND date = CURDATE()
    ");
    $stmt->execute([$employeeId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attendance && $attendance['time_in']) {

        // compute hours every logout
        $timeIn = strtotime($attendance['time_in']);
        $timeOut = time();

        $secondsWorked = $timeOut - $timeIn;
        $hoursWorked = round($secondsWorked / 3600, 2);

        $stmt = $pdo->prepare("
            UPDATE attendance
            SET 
                time_out = NOW(),
                total_work_hours = ?,
                status = 'completed'
            WHERE employee_id = ?
            AND date = CURDATE()
        ");
        $stmt->execute([$hoursWorked, $employeeId]);
    }

    $status = "timed_out";
}

// response
header('Content-Type: application/json');
echo json_encode(["status" => $status]);