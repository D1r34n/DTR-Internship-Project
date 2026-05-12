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

// All dates already covered by any pending/approved leave or OB request
$leaveStmt = $pdo->prepare("
    SELECT selected_dates
    FROM leave_requests
    WHERE employee_id = ?
    AND status IN ('pending', 'approved')
");
$leaveStmt->execute([$employeeId]);

$blockedDates = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $decoded = json_decode($row['selected_dates'], true);
    if (is_array($decoded)) {
        $blockedDates = array_merge($blockedDates, $decoded);
    }
}
$blockedDates = array_values(array_unique($blockedDates));

echo json_encode([
    'scheduledDates' => $scheduledDates,
    'obDates'        => $blockedDates,
]);
