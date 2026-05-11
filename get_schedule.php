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

// ---- GET SCHEDULES (approved only) ----
$stmt = $pdo->prepare("
    SELECT * FROM schedules
    WHERE employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    AND status = 'approved'
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

// ---- GET OB REQUESTS ----
$obStmt = $pdo->prepare("
    SELECT ob_date, status
    FROM ob_requests
    WHERE employee_id = ?
    AND ob_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $start, $end]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}

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
            'backgroundColor' => 'var(--warning-glass)',
            'borderColor'     => 'var(--warning-border)',
            'textColor'       => 'var(--warning-color)',
            'extendedProps'   => ['is_rest_day' => true]
        ];
        continue;
    }

    $startDT     = $row['scheduled_start'];
    $endDT       = $row['scheduled_end'];
    $date        = $row['schedule_date'];

    if (!$startDT || !$endDT) continue;

    $startDate    = date('Y-m-d', strtotime($startDT));
    $endDate      = date('Y-m-d', strtotime($endDT));
    $isOvernight  = $endDate > $startDate;

    // Night shift = starts 6pm–5:59am; Day shift = starts 6am–5:59pm
    $startHour    = (int)date('H', strtotime($startDT));
    $isNightShift = ($startHour >= 18 || $startHour < 6);

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

    // ---- CHECK OB STATUS FOR THIS DATE ----
    $obStatus = $obMap[$date] ?? null;

    if ($obStatus === 'approved') {
        $events[] = [
            'title'           => 'On OB',
            'start'           => $date,
            'backgroundColor' => '#6f42c1',
            'borderColor'     => '#59359a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'ob_approved']
        ];
        continue;

    } elseif ($obStatus === 'pending') {
        $events[] = [
            'title'           => 'OB Pending',
            'start'           => $date,
            'backgroundColor' => '#f0ad4e',
            'borderColor'     => '#d99a3a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'ob_pending']
        ];
        continue;

    } elseif ($obStatus === 'rejected') {
        $events[] = [
            'title'           => 'OB Rejected',
            'start'           => $date,
            'backgroundColor' => '#dc3545',
            'borderColor'     => '#b02a37',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'ob_rejected']
        ];
        // Don't continue — fall through to also show the shift
    }

    // ---- REGULAR SHIFT ----
    if ($isNightShift) {
        // ---- NIGHT SHIFT ----
        $events[] = [
            'title'           => $startTimeStr . ' – ' . $endTimeStr . ($isOvernight ? ' ↪' : ''),
            'start'           => $date,
            'backgroundColor' => '#4da3ff',
            'borderColor'     => '#2e8fe8',
            'textColor'       => '#ffffff',
            'extendedProps'   => [
                'is_rest_day'  => false,
                'is_overnight' => $isOvernight,
                'shift_type'   => 'night_start'
            ]
        ];

        // ---- OVERNIGHT CONTINUATION (only when shift actually crosses midnight) ----
        if ($isOvernight && $endDate <= $end) {
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
    } else {
        // ---- DAY SHIFT ----
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

// ---- OB EVENTS FOR DATES WITHOUT SCHEDULE ----
foreach ($obMap as $obDate => $obStatus) {
    if (in_array($obDate, $scheduleDates)) continue;
    if ($obDate < $start || $obDate > $end) continue;

    if ($obStatus === 'approved') {
        $events[] = [
            'title'           => 'On OB',
            'start'           => $obDate,
            'backgroundColor' => '#6f42c1',
            'borderColor'     => '#59359a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'ob_approved']
        ];
    } elseif ($obStatus === 'pending') {
        $events[] = [
            'title'           => 'OB Pending',
            'start'           => $obDate,
            'backgroundColor' => '#f0ad4e',
            'borderColor'     => '#d99a3a',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'ob_pending']
        ];
    } elseif ($obStatus === 'rejected') {
        $events[] = [
            'title'           => 'Rejected OB',
            'start'           => $obDate,
            'backgroundColor' => '#dc3545',
            'borderColor'     => '#b02a37',
            'textColor'       => '#ffffff',
            'extendedProps'   => ['shift_type' => 'ob_rejected']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(array_values($events));