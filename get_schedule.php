<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

require_once 'db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];
$start      = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-01');
$end        = isset($_GET['end'])   ? substr($_GET['end'],   0, 10) : date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT * FROM schedules
    WHERE employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date ASC
");
$stmt->execute([$employeeId, $start, $end]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$events = [];

foreach ($schedules as $row) {

    // ---- REST DAY ----
    if ($row['is_rest_day']) {
        $events[] = [
            'title'           => 'Rest Day',
            'start'           => $row['schedule_date'],
            'backgroundColor' => '#6c757d',
            'borderColor'     => '#6c757d',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['is_rest_day' => true]
        ];
        continue;
    }

    $startDT  = $row['scheduled_start_datetime'];
    $endDT    = $row['scheduled_end_datetime'];

    if (!$startDT || !$endDT) continue;

    $startDate    = date('Y-m-d', strtotime($startDT));
    $endDate      = date('Y-m-d', strtotime($endDT));
    $isOvernight  = $endDate > $startDate;

    $startTimeStr = date('h:i A', strtotime($startDT));
    $endTimeStr   = date('h:i A', strtotime($endDT));

    if (!$isOvernight) {
        // ---- REGULAR DAY SHIFT ----
        $events[] = [
            'title'           => $startTimeStr . ' – ' . $endTimeStr,
            'start'           => $row['schedule_date'],
            'backgroundColor' => '#97be41',
            'borderColor'     => '#7fae2f',
            'textColor'       => '#ffffff',
            'extendedProps'   => [
                'is_rest_day'  => false,
                'is_overnight' => false,
                'shift_type'   => 'day'
            ]
        ];
    } else {
        // ---- OVERNIGHT SHIFT — two events ----

        // Event 1: Start day — show full time range
        $events[] = [
            'title'           => $startTimeStr . ' – ' . $endTimeStr . ' ↪',
            'start'           => $row['schedule_date'],
            'backgroundColor' => '#4da3ff',
            'borderColor'     => '#2e8fe8',
            'textColor'       => '#ffffff',
            'extendedProps'   => [
                'is_rest_day'  => false,
                'is_overnight' => true,
                'shift_type'   => 'night_start'
            ]
        ];

        // Event 2: Next day continuation — only if within the requested range
        if ($endDate <= $end) {
            $events[] = [
                'title'           => '↪ until ' . $endTimeStr,
                'start'           => $endDate,
                'backgroundColor' => 'rgba(77, 163, 255, 0.3)',
                'borderColor'     => '#4da3ff',
                'textColor'       => '#4da3ff',
                'extendedProps'   => [
                    'is_rest_day'  => false,
                    'is_overnight' => true,
                    'shift_type'   => 'night_continuation'
                ]
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode(array_values($events));