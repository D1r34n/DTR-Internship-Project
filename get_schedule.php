<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

require_once 'db.php';

$employeeId = $_SESSION['user_id'];
$start      = $_GET['start'] ?? date('Y-m-01');
$end        = $_GET['end']   ?? date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT * FROM schedules
    WHERE employee_id = ?
    AND work_date BETWEEN ? AND ?
    ORDER BY work_date ASC
");
$stmt->execute([$employeeId, $start, $end]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = array_map(fn($row) => [
    'title'           => $row['is_rest_day']
        ? 'Rest Day'
        : date('h:i A', strtotime('1970-01-01 ' . $row['time_in'])) . ' – ' . date('h:i A', strtotime('1970-01-01 ' . $row['time_out'])),
    'start'           => $row['work_date'],
    'backgroundColor' => $row['is_rest_day'] ? '#6c757d' : '#97be41',
    'borderColor'     => $row['is_rest_day'] ? '#6c757d' : '#7fae2f',
    'textColor'       => '#ffffff',
    'extendedProps'   => [
        'is_rest_day' => (bool)$row['is_rest_day'],
        'is_today'    => $row['work_date'] === date('Y-m-d'),
    ]
], $schedules);

header('Content-Type: application/json');
echo json_encode(array_values($events));