<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once '../db.php';
require_once '../system_functions/system_service.php';

header('Content-Type: application/json');

$employeeId = $_SESSION['user_id'];

$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate   = $_GET['end'] ?? date('Y-m-t');

echo json_encode([
    'records'   => getAttendanceRecords($pdo, $employeeId, $startDate, $endDate),
    'schedules' => getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate)
]);