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
    AND leave_type != 'ob leave'
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
    SELECT start_date AS ob_date, status
    FROM leave_requests
    WHERE employee_id = ?
    AND leave_type = 'ob leave'
    AND start_date BETWEEN ? AND ?
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
        foreach ($dates as $dateStr) {
            if ($dateStr >= $start && $dateStr <= $end) {
                $leaveMap[$dateStr] = $leave['status'];
            }
        }
    } else {
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
            'title'         => 'Rest Day',
            'start'         => $row['schedule_date'],
            'allDay'        => true,
            'classNames'    => ['fc-ev-rest'],
            'extendedProps' => ['shift_type' => 'rest', 'is_rest_day' => true],
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

    $startHour    = (int)date('H', strtotime($startDT));
    $isNightShift = ($startHour >= 18 || $startHour < 6);

    $startTimeStr = date('g:i A', strtotime($startDT));
    $endTimeStr   = date('g:i A', strtotime($endDT));

    // ---- CHECK LEAVE STATUS FOR THIS DATE ----
    $leaveStatus = $leaveMap[$date] ?? null;

    if ($leaveStatus === 'approved') {
        $events[] = [
            'title'         => 'On Leave',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-on-leave'],
            'extendedProps' => ['shift_type' => 'leave_approved'],
        ];
        continue;

    } elseif ($leaveStatus === 'pending') {
        $events[] = [
            'title'         => 'Leave Pending',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-leave-pending'],
            'extendedProps' => ['shift_type' => 'leave_pending'],
        ];
        continue;

    } elseif ($leaveStatus === 'rejected') {
        $events[] = [
            'title'         => 'Leave Rejected',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-leave-rejected'],
            'extendedProps' => ['shift_type' => 'leave_rejected'],
        ];
        // Don't continue — fall through to also show the shift
    }

    // ---- CHECK OB STATUS FOR THIS DATE ----
    $obStatus = $obMap[$date] ?? null;

    if ($obStatus === 'approved') {
        $events[] = [
            'title'         => 'On OB',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-on-ob'],
            'extendedProps' => ['shift_type' => 'ob_approved'],
        ];
        continue;

    } elseif ($obStatus === 'pending') {
        $events[] = [
            'title'         => 'OB Pending',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-ob-pending'],
            'extendedProps' => ['shift_type' => 'ob_pending'],
        ];
        continue;

    } elseif ($obStatus === 'rejected') {
        $events[] = [
            'title'         => 'OB Rejected',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-leave-rejected'],
            'extendedProps' => ['shift_type' => 'ob_rejected'],
        ];
        // Don't continue — fall through to also show the shift
    }

    // ---- REGULAR SHIFT ----
    if ($isNightShift) {
        $events[] = [
            'title'         => 'Night Shift',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-night'],
            'extendedProps' => [
                'is_rest_day'  => false,
                'is_overnight' => $isOvernight,
                'shift_type'   => 'night',
                'timeInStr'    => $startTimeStr,
                'timeOutStr'   => $endTimeStr . ($isOvernight ? ' ↪' : ''),
            ],
        ];

        if ($isOvernight && $endDate <= $end) {
            $events[] = [
                'title'         => '↪ until ' . $endTimeStr,
                'start'         => $endDate,
                'allDay'        => true,
                'classNames'    => ['fc-ev-night-cont'],
                'extendedProps' => [
                    'is_rest_day'  => false,
                    'is_overnight' => true,
                    'shift_type'   => 'night_continuation',
                ],
            ];
        }
    } else {
        $events[] = [
            'title'         => 'Day Shift',
            'start'         => $date,
            'allDay'        => true,
            'classNames'    => ['fc-ev-day'],
            'extendedProps' => [
                'is_rest_day'  => false,
                'is_overnight' => false,
                'shift_type'   => 'day',
                'timeInStr'    => $startTimeStr,
                'timeOutStr'   => $endTimeStr,
            ],
        ];
    }
}

// ---- LEAVE EVENTS FOR DATES WITHOUT SCHEDULE ----
foreach ($leaveMap as $leaveDate => $leaveStatus) {
    if (in_array($leaveDate, $scheduleDates)) continue;
    if ($leaveDate < $start || $leaveDate > $end) continue;

    if ($leaveStatus === 'approved') {
        $events[] = [
            'title'         => 'On Leave',
            'start'         => $leaveDate,
            'allDay'        => true,
            'classNames'    => ['fc-ev-on-leave'],
            'extendedProps' => ['shift_type' => 'leave_approved'],
        ];
    } elseif ($leaveStatus === 'pending') {
        $events[] = [
            'title'         => 'Leave Pending',
            'start'         => $leaveDate,
            'allDay'        => true,
            'classNames'    => ['fc-ev-leave-pending'],
            'extendedProps' => ['shift_type' => 'leave_pending'],
        ];
    } elseif ($leaveStatus === 'rejected') {
        $events[] = [
            'title'         => 'Leave Rejected',
            'start'         => $leaveDate,
            'allDay'        => true,
            'classNames'    => ['fc-ev-leave-rejected'],
            'extendedProps' => ['shift_type' => 'leave_rejected'],
        ];
    }
}

// ---- OB EVENTS FOR DATES WITHOUT SCHEDULE ----
foreach ($obMap as $obDate => $obStatus) {
    if (in_array($obDate, $scheduleDates)) continue;
    if ($obDate < $start || $obDate > $end) continue;

    if ($obStatus === 'approved') {
        $events[] = [
            'title'         => 'On OB',
            'start'         => $obDate,
            'allDay'        => true,
            'classNames'    => ['fc-ev-on-ob'],
            'extendedProps' => ['shift_type' => 'ob_approved'],
        ];
    } elseif ($obStatus === 'pending') {
        $events[] = [
            'title'         => 'OB Pending',
            'start'         => $obDate,
            'allDay'        => true,
            'classNames'    => ['fc-ev-ob-pending'],
            'extendedProps' => ['shift_type' => 'ob_pending'],
        ];
    } elseif ($obStatus === 'rejected') {
        $events[] = [
            'title'         => 'OB Rejected',
            'start'         => $obDate,
            'allDay'        => true,
            'classNames'    => ['fc-ev-leave-rejected'],
            'extendedProps' => ['shift_type' => 'ob_rejected'],
        ];
    }
}

// ---- ADMIN: all employees' leave & OB events ----
if (($_SESSION['user_role'] ?? '') === 'admin') {

    $stmt = $pdo->prepare("
        SELECT lr.selected_dates, lr.start_date, lr.end_date, lr.status,
               lr.leave_type, lr.reason,
               CONCAT(e.first_name, ' ', e.last_name) AS full_name
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        WHERE lr.leave_type != 'ob leave'
          AND (
              lr.start_date BETWEEN ? AND ?
              OR lr.end_date   BETWEEN ? AND ?
              OR (lr.start_date <= ? AND lr.end_date >= ?)
          )
    ");
    $stmt->execute([$start, $end, $start, $end, $start, $end]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
        $name  = $leave['full_name'];
        $dates = json_decode($leave['selected_dates'], true);

        if (is_array($dates) && !empty($dates)) {
            $datesToShow = array_filter($dates, fn($d) => $d >= $start && $d <= $end);
        } else {
            $datesToShow = [];
            $cur = new DateTime($leave['start_date']);
            $fin = new DateTime($leave['end_date']);
            while ($cur <= $fin) {
                $d = $cur->format('Y-m-d');
                if ($d >= $start && $d <= $end) $datesToShow[] = $d;
                $cur->modify('+1 day');
            }
        }

        $leaveProps = [
            'employee_name' => $name,
            'leave_type'    => $leave['leave_type'],
            'reason'        => $leave['reason'],
        ];
        foreach ($datesToShow as $d) {
            if ($leave['status'] === 'approved') {
                $events[] = ['title' => $name . ' – On Leave',       'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-on-leave'],      'extendedProps' => ['shift_type' => 'leave_approved']];
            } elseif ($leave['status'] === 'pending') {
                $events[] = ['title' => $name . ' – Leave Pending',  'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-leave-pending'], 'extendedProps' => ['shift_type' => 'leave_pending']];
            } elseif ($leave['status'] === 'rejected') {
                $events[] = ['title' => $name . ' – Leave Rejected', 'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-leave-rejected'],'extendedProps' => ['shift_type' => 'leave_rejected']];
            }
        }
    }

    $stmt = $pdo->prepare("
        SELECT lr.start_date AS ob_date, lr.status, lr.reason,
               CONCAT(e.first_name, ' ', e.last_name) AS full_name
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        WHERE lr.leave_type = 'ob leave'
          AND lr.start_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
        $name   = $ob['full_name'];
        $d      = $ob['ob_date'];
        $obProps = ['employee_name' => $name, 'leave_type' => 'OB Leave', 'reason' => $ob['reason']];
        if ($ob['status'] === 'approved') {
            $events[] = ['title' => $name . ' – On OB',       'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-on-ob'],         'extendedProps' => ['shift_type' => 'ob_approved']];
        } elseif ($ob['status'] === 'pending') {
            $events[] = ['title' => $name . ' – OB Pending',  'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-ob-pending'],    'extendedProps' => ['shift_type' => 'ob_pending']];
        } elseif ($ob['status'] === 'rejected') {
            $events[] = ['title' => $name . ' – OB Rejected', 'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-leave-rejected'],'extendedProps' => ['shift_type' => 'ob_rejected']];
        }
    }
}

header('Content-Type: application/json');
echo json_encode(array_values($events));
