<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];
$today      = date('Y-m-d');

// Get all future scheduled work days (non-rest days)
$stmt = $pdo->prepare("
    SELECT schedule_date
    FROM schedules
    WHERE employee_id = ?
    AND is_rest_day = 0
    AND schedule_date > ?
    ORDER BY schedule_date ASC
");

$stmt->execute([$employeeId, $today]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dates = array_column($rows, 'schedule_date');

echo json_encode($dates);