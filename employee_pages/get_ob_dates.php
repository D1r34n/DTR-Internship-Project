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

// All scheduled non-rest work days
$stmt = $pdo->prepare("
    SELECT schedule_date
    FROM schedules
    WHERE employee_id = ?
    AND is_rest_day = 0
    ORDER BY schedule_date ASC
");
$stmt->execute([$employeeId]);
$scheduledDates = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'schedule_date');

// Dates already covered by pending or approved OB requests
$obStmt = $pdo->prepare("
    SELECT ob_date
    FROM ob_requests
    WHERE employee_id = ?
    AND status IN ('pending', 'approved')
");
$obStmt->execute([$employeeId]);
$obDates = array_column($obStmt->fetchAll(PDO::FETCH_ASSOC), 'ob_date');

echo json_encode([
    'scheduledDates' => $scheduledDates,
    'obDates'        => $obDates,
]);
