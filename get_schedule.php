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

// ---- GET SCHEDULES ----
$stmt = $pdo->prepare("
    SELECT * FROM schedules
    WHERE employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date ASC
");
$stmt->execute([$employeeId, $start, $end]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- GET LEAVE REQUESTS ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND (
        start_date BETWEEN ? AND ?
        OR end_date BETWEEN ? AND ?
        OR (start_date <= ? AND end_date >= ?)
    )
");
$leaveStmt->execute([$employeeId, $start, $end, $start, $end, $start, $end]);
$leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- BUILD LEAVE MAP (using actual selected dates) ----
$leaveMap = [];
foreach ($leaveRequests as $leave) {
    $dates = json_decode($leave['selected_dates'], true);

    if (is_array($dates) && !empty($dates)) {
        // Use actual selected dates
        foreach ($dates as $dateStr) {
            if ($dateStr >= $start && $dateStr <= $end) {
                $leaveMap[$dateStr] = $leave['status'];
            }
        }
    } else {
        // Fallback for old records without selected_dates
        $current = new DateTime($leave['start_date']);
        $endDate = new DateTime($leave['end_date']);
        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');
            $dow     = (int) $current->format('N');
            if ($dow < 6 && $dateStr >= $start && $dateStr <= $end) {
                $leaveMap[$dateStr] = $leave['status'];
            }
            $current->modify('+1 day');
        }
    }
}
$events        = [];
$scheduleDates = array_column($schedules, 'schedule_date');

// ---- LOOP THROUGH SCHEDULES ----
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

    $startDT     = $row['scheduled_start'];
    $endDT       = $row['scheduled_end'];
    $date        = $row['schedule_date'];

    if (!$startDT || !$endDT) continue;

    $startDate   = date('Y-m-d', strtotime($startDT));
    $endDate     = date('Y-m-d', strtotime($endDT));
    $isOvernight = $endDate > $startDate; 

    // 6pm to 5:59AM is night shift
    // 6am is dayshift

    $startTimeStr = date('h:i A', strtotime($startDT));
    $endTimeStr   = date('h:i A', strtotime($endDT));

    // ---- CHECK LEAVE STATUS FOR THIS DATE ----
    $leaveStatus = $leaveMap[$date] ?? null;

    if ($leaveStatus === 'approved') {
        $events[] = [
            'title'           => 'On Leave',
            'start'           => $date,
            'backgroundColor' => '#fd7e14',
            'borderColor'     => '#e8610a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'leave_approved']
        ];
        continue;

    } elseif ($leaveStatus === 'pending') {
        $events[] = [
            'title'           => 'Leave Pending',
            'start'           => $date,
            'backgroundColor' => '#f0ad4e',
            'borderColor'     => '#d99a3a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'leave_pending']
        ];
        continue;

    } elseif ($leaveStatus === 'rejected') {
        $events[] = [
            'title'           => 'Leave Rejected',
            'start'           => $date,
            'backgroundColor' => '#dc3545',
            'borderColor'     => '#b02a37',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'leave_rejected']
        ];
        // Don't continue — fall through to also show the shift
    }

    // ---- REGULAR SHIFT ----
    if (!$isOvernight) {
        $events[] = [
            'title'           => $startTimeStr . ' – ' . $endTimeStr,
            'start'           => $date,
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
        // ---- OVERNIGHT SHIFT START ----
        $events[] = [
            'title'           => $startTimeStr . ' – ' . $endTimeStr . ' ↪',
            'start'           => $date,
            'backgroundColor' => '#4da3ff',
            'borderColor'     => '#2e8fe8',
            'textColor'       => '#ffffff',
            'extendedProps'   => [
                'is_rest_day'  => false,
                'is_overnight' => true,
                'shift_type'   => 'night_start'
            ]
        ];

        // ---- OVERNIGHT CONTINUATION ----
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

// ---- LEAVE EVENTS FOR DATES WITHOUT SCHEDULE ----
// Handles leave on days that have no schedule entry (e.g. May 4, 5)
foreach ($leaveMap as $leaveDate => $leaveStatus) {
    if (in_array($leaveDate, $scheduleDates)) continue;
    if ($leaveDate < $start || $leaveDate > $end) continue;

    if ($leaveStatus === 'approved') {
        $events[] = [
            'title'           => 'On Leave',
            'start'           => $leaveDate,
            'backgroundColor' => '#fd7e14',
            'borderColor'     => '#e8610a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'leave_approved']
        ];
    } elseif ($leaveStatus === 'pending') {
        $events[] = [
            'title'           => 'Leave Pending',
            'start'           => $leaveDate,
            'backgroundColor' => '#f0ad4e',
            'borderColor'     => '#d99a3a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'leave_pending']
        ];
    } elseif ($leaveStatus === 'rejected') {
        $events[] = [
            'title'           => 'Leave Rejected',
            'start'           => $leaveDate,
            'backgroundColor' => '#dc3545',
            'borderColor'     => '#b02a37',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'leave_rejected']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(array_values($events));