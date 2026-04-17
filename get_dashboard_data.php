<?php
session_start();
require_once 'db.php';

$employeeId = $_SESSION['user_id'];

/* CHECK STATUS */
$stmt = $pdo->prepare("
    SELECT log_type 
    FROM logs 
    WHERE employee_id = ?
    ORDER BY log_time DESC 
    LIMIT 1
");
$stmt->execute([$employeeId]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

$timedIn = ($lastLog && $lastLog['log_type'] === 'login');

/* WEEKLY HOURS */
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$endOfWeek = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT ROUND(SUM(daily_hours), 2) AS weekly_hours
    FROM (
        SELECT 
            DATE(log_time) as work_date,
            TIMESTAMPDIFF(MINUTE, 
                MIN(CASE WHEN log_type = 'login' THEN log_time END),
                MAX(CASE WHEN log_type = 'logout' THEN log_time END)
            ) / 60 AS daily_hours
        FROM logs
        WHERE employee_id = ?
        AND DATE(log_time) BETWEEN ? AND ?
        GROUP BY DATE(log_time)
    ) AS daily_totals
");

$stmt->execute([$employeeId, $startOfWeek, $endOfWeek]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$startOfMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT ROUND(SUM(daily_hours), 2) AS monthly_hours
    FROM (
        SELECT 
            DATE(log_time) as work_date,
            TIMESTAMPDIFF(MINUTE, 
                MIN(CASE WHEN log_type = 'login' THEN log_time END),
                MAX(CASE WHEN log_type = 'logout' THEN log_time END)
            ) / 60 AS daily_hours
        FROM logs
        WHERE employee_id = ?
        AND DATE(log_time) BETWEEN ? AND ?
        GROUP BY DATE(log_time)
    ) AS daily_totals
");

$stmt->execute([$employeeId, $startOfMonth, $endOfMonth]);
$rowMonth = $stmt->fetch(PDO::FETCH_ASSOC);

$monthlyHours = $rowMonth['monthly_hours'] ?? 0;
$weeklyHours = $row['weekly_hours'] ?? 0;

echo json_encode([
    "timedIn" => $timedIn,
    "weeklyHours" => $weeklyHours,
    "monthlyHours" => $monthlyHours
]);