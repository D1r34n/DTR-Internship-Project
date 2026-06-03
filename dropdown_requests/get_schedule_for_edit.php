<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];
$today      = date('Y-m-d');
$cutoff     = date('Y-m-d', strtotime('+60 days'));

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.schedule_date,
        s.scheduled_start,
        s.scheduled_end,
        s.is_rest_day,
        s.batch_id,
        COALESCE(ser.status, NULL) AS edit_status
    FROM schedules s
    LEFT JOIN schedule_edit_requests ser ON ser.batch_id = s.batch_id
    WHERE s.employee_id = ?
    AND s.schedule_date >= ?
    AND s.schedule_date <= ?
    AND s.is_rest_day = 0
    AND COALESCE(s.is_archived, 0) = 0
    ORDER BY s.schedule_date ASC
");
$stmt->execute([$employeeId, $today, $cutoff]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($rows);
