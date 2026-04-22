<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

require_once 'db.php';

$employeeId = $_SESSION['user_id'];
$start = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-01');
$end   = isset($_GET['end'])   ? substr($_GET['end'],   0, 10) : date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT * FROM schedules
    WHERE employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date ASC
");
$stmt->execute([$employeeId, $start, $end]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log("start=$start end=$end count=" . count($schedules));

$events = array_map(fn($row) => [
    'title'           => $row['is_rest_day']
        ? 'Rest Day'
        : date('h:i A', strtotime($row['scheduled_start_datetime'])) . ' – ' . date('h:i A', strtotime($row['scheduled_end_datetime'])),
    'start'           => $row['schedule_date'],
    'backgroundColor' => $row['is_rest_day'] ? '#6c757d' : '#97be41',
    'borderColor'     => $row['is_rest_day'] ? '#6c757d' : '#7fae2f',
    'textColor'       => '#ffffff',
    'extendedProps'   => [
        'is_rest_day' => (bool)$row['is_rest_day'],
        'is_today'    => $row['schedule_date'] === date('Y-m-d'),
    ]
], $schedules);

header('Content-Type: application/json');
echo json_encode(array_values($events));