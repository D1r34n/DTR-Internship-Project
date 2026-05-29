<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

require_once 'db.php';
date_default_timezone_set('Asia/Manila');

// Ensure json output header is declared uniformly
header('Content-Type: application/json');

try { 
    $pdo->exec("ALTER TABLE schedules ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0"); 
} catch (PDOException $e) {}

// Admin viewing a specific employee's calendar — richer scoped format
$userRole = $_SESSION['user_role'] ?? 'employee';

$allowedRoles = [
    'superadmin',
    'admin',
    'manager',
    'workforce'
];

$scopedToEmployee = isset($_GET['employee_id']) && in_array($userRole, $allowedRoles, true);

if ($scopedToEmployee) {
    $employeeId = intval($_GET['employee_id']);
    if (!$employeeId) { echo '[]'; exit(); }

    $firstDay = date('Y-m-d', strtotime($_GET['start'] ?? date('Y-m-01')));
    $lastDay  = date('Y-m-d', strtotime(($_GET['end'] ?? date('Y-m-t')) . ' -1 day'));

    // Note: Removed "AND status != 'rejected'" as schedules do not have a status column natively.
    $stmt = $pdo->prepare("
        SELECT schedule_date, scheduled_start, scheduled_end, is_rest_day
        FROM schedules
        WHERE employee_id = ? AND schedule_date BETWEEN ? AND ?
          AND COALESCE(is_archived, 0) = 0
        ORDER BY schedule_date
    ");
    $stmt->execute([$employeeId, $firstDay, $lastDay]);
    $schedMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $schedMap[$row['schedule_date']] = $row;
    }

    $leaveStmt = $pdo->prepare("
        SELECT start_date, end_date, selected_dates, status
        FROM leave_requests
        WHERE employee_id = ? AND leave_type_id != (SELECT id FROM leave_types WHERE name = 'ob leave')
        AND (start_date <= ? AND end_date >= ?)
    ");
    $leaveStmt->execute([$employeeId, $lastDay, $firstDay]);
    $leaveMap = [];
    foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
        $dates = json_decode($leave['selected_dates'], true);
        if (is_array($dates) && !empty($dates)) {
            foreach ($dates as $d) {
                if ($d >= $firstDay && $d <= $lastDay) $leaveMap[$d] = $leave['status'];
            }
        } else {
            $cur = new DateTime($leave['start_date']);
            $end = new DateTime($leave['end_date']);
            while ($cur <= $end) {
                $d = $cur->format('Y-m-d');
                if ($d >= $firstDay && $d <= $lastDay) $leaveMap[$d] = $leave['status'];
                $cur->modify('+1 day');
            }
        }
    }

    $obStmt = $pdo->prepare("
        SELECT start_date, end_date, selected_dates, status
        FROM leave_requests
        WHERE employee_id = ? AND leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave')
        AND (start_date <= ? AND end_date >= ?)
    ");
    $obStmt->execute([$employeeId, $lastDay, $firstDay]);
    $obMap = [];
    foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
        $dates = json_decode($ob['selected_dates'], true);
        if (is_array($dates) && !empty($dates)) {
            foreach ($dates as $d) {
                if ($d >= $firstDay && $d <= $lastDay) $obMap[$d] = $ob['status'];
            }
        } else {
            $cur = new DateTime($ob['start_date']);
            $end = new DateTime($ob['end_date']);
            while ($cur <= $end) {
                $d = $cur->format('Y-m-d');
                if ($d >= $firstDay && $d <= $lastDay) $obMap[$d] = $ob['status'];
                $cur->modify('+1 day');
            }
        }
    }

    $nightContDates = [];
    foreach ($schedMap as $date => $sched) {
        if ($sched['is_rest_day'] || !$sched['scheduled_start'] || !$sched['scheduled_end']) continue;
        $endDate = date('Y-m-d', strtotime($sched['scheduled_end']));
        if ($endDate > $date && $endDate <= $lastDay) $nightContDates[$endDate] = $date;
    }

    $events   = [];
    $allDates = array_unique(array_merge(
        array_keys($schedMap), array_keys($leaveMap),
        array_keys($obMap), array_keys($nightContDates)
    ));
    sort($allDates);

    foreach ($allDates as $dateStr) {
        $sched       = $schedMap[$dateStr] ?? null;
        $leaveStatus = $leaveMap[$dateStr] ?? null;
        $obStatus    = $obMap[$dateStr]    ?? null;
        $isNightCont = isset($nightContDates[$dateStr]);

        if ($isNightCont) {
            $originSched = $schedMap[$nightContDates[$dateStr]] ?? null;
            $contTimeStr = $originSched
                ? 'until ' . date('g:i A', strtotime($originSched['scheduled_end']))
                : 'Night (cont.)';
            $events[] = [
                'id' => 'night-cont-' . $dateStr, 'title' => $contTimeStr,
                'start' => $dateStr, 'allDay' => true,
                'classNames' => ['fc-ev-night-cont'],
                'extendedProps' => [
                    'type' => 'night-cont', 'dateStr' => $dateStr,
                    'hasSchedule' => false, 'hasActiveLeaveOrOB' => false,
                    'timeInStr' => null, 'timeOutStr' => null,
                    'schedInVal' => null, 'schedOutVal' => null, 'isRestDay' => false,
                ],
            ];
        }

        $hasActiveLeaveOrOB =
            in_array($leaveStatus, ['approved', 'pending']) ||
            in_array($obStatus,    ['approved', 'pending']);

        $eventType = $eventTitle = $timeInStr = $timeOutStr = $schedInVal = $schedOutVal = null;
        $isRestDay = $isRejectedLeaveOrOB = false;

        if ($leaveStatus === 'approved')       { $eventType = 'on-leave';         $eventTitle = 'On Leave'; }
        elseif ($obStatus === 'approved')      { $eventType = 'on-ob';            $eventTitle = 'On OB'; }
        elseif ($leaveStatus === 'pending')    { $eventType = 'leave-pending';    $eventTitle = 'Leave Pending'; }
        elseif ($obStatus === 'pending')       { $eventType = 'ob-pending';       $eventTitle = 'OB Pending'; }
        elseif ($leaveStatus === 'rejected')   { $eventType = 'leave-rejected';   $eventTitle = 'Leave Rejected'; $isRejectedLeaveOrOB = true; }
        elseif ($obStatus === 'rejected')      { $eventType = 'leave-rejected';   $eventTitle = 'OB Rejected';   $isRejectedLeaveOrOB = true; }
        elseif ($sched && $sched['is_rest_day']) {
            $eventType = 'rest'; $eventTitle = 'Rest Day'; $isRestDay = true;
        } elseif ($sched) {
            // Because there is no status on schedule, it falls directly to regular shifts
            $endTs = strtotime($sched['scheduled_end']);
            $isOvernight = date('Y-m-d', $endTs) > $dateStr;
            $sh = (int)date('H', strtotime($sched['scheduled_start']));
            $eventType  = ($sh >= 18 || $sh < 6) ? 'night'       : 'day';
            $eventTitle = ($sh >= 18 || $sh < 6) ? 'Night Shift' : 'Day Shift';
            $timeInStr  = date('g:i A', strtotime($sched['scheduled_start']));
            $timeOutStr = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
            $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
            $schedOutVal = date('H:i', $endTs);
        }

        if ($isRejectedLeaveOrOB && $sched && !$sched['is_rest_day']) {
            $endTs = strtotime($sched['scheduled_end']);
            $isOvernight = date('Y-m-d', $endTs) > $dateStr;
            $timeInStr  = date('g:i A', strtotime($sched['scheduled_start']));
            $timeOutStr = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
            $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
            $schedOutVal = date('H:i', $endTs);
        }

        if ($eventType === null) continue;

        $events[] = [
            'id' => 'main-' . $dateStr, 'title' => $eventTitle,
            'start' => $dateStr, 'allDay' => true,
            'classNames' => ['fc-ev-' . $eventType],
            'extendedProps' => [
                'type'               => $eventType,
                'dateStr'            => $dateStr,
                'schedInVal'         => $schedInVal,
                'schedOutVal'        => $schedOutVal,
                'isRestDay'          => $isRestDay,
                'hasActiveLeaveOrOB' => $hasActiveLeaveOrOB,
                'hasSchedule'        => $sched !== null,
                'timeInStr'          => $timeInStr,
                'timeOutStr'         => $timeOutStr,
            ],
        ];
    }

    echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$employeeId = $_SESSION['user_id'];
$start      = isset($_GET['start']) ? substr($_GET['start'], 0, 10) : date('Y-m-01');
$end        = isset($_GET['end'])   ? substr($_GET['end'],   0, 10) : date('Y-m-t');

// ---- GET SCHEDULES (Removed non-existent status structural check) ----
$stmt = $pdo->prepare("
    SELECT * FROM schedules
    WHERE employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    AND COALESCE(is_archived, 0) = 0
    ORDER BY schedule_date ASC
");
$stmt->execute([$employeeId, $start, $end]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- GET LEAVE REQUESTS (Fixed to use leave_type_id reference structural block) ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND leave_type_id != (SELECT id FROM leave_types WHERE name = 'ob leave')
    AND (
        start_date BETWEEN ? AND ?
        OR end_date BETWEEN ? AND ?
        OR (start_date <= ? AND end_date >= ?)
    )
");
$leaveStmt->execute([$employeeId, $start, $end, $start, $end, $start, $end]);
$leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- GET OB REQUESTS (Fixed to use leave_type_id subquery structural block) ----
$obStmt = $pdo->prepare("
    SELECT start_date AS ob_date, status
    FROM leave_requests
    WHERE employee_id = ?
    AND leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave')
    AND start_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $start, $end]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}

// ---- BUILD LEAVE MAP ----
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
    }

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
    }

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

// ---- ADMIN GLOBAL PULL (superadmin role overview) ----
if (($_SESSION['user_role'] ?? '') === 'superadmin') {

    $stmt = $pdo->prepare("
        SELECT lr.selected_dates, lr.start_date, lr.end_date, lr.status,
               lt.name AS leave_type, lr.reason,
               CONCAT(e.first_name, ' ', e.last_name) AS full_name
        FROM leave_requests lr
        JOIN leave_types lt ON lt.id = lr.leave_type_id
        JOIN employees e ON e.id = lr.employee_id
        WHERE lt.name != 'ob leave'
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
        WHERE lr.leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave')
          AND lr.start_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
        $name   = $ob['full_name'];
        $d      = $ob['ob_date'];
        if ($ob['status'] === 'approved') {
            $events[] = ['title' => $name . ' – On OB',       'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-on-ob'],         'extendedProps' => ['shift_type' => 'ob_approved']];
        } elseif ($ob['status'] === 'pending') {
            $events[] = ['title' => $name . ' – OB Pending',  'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-ob-pending'],    'extendedProps' => ['shift_type' => 'ob_pending']];
        } elseif ($ob['status'] === 'rejected') {
            $events[] = ['title' => $name . ' – OB Rejected', 'start' => $d, 'allDay' => true, 'classNames' => ['fc-ev-leave-rejected'],'extendedProps' => ['shift_type' => 'ob_rejected']];
        }
    }
}

echo json_encode(array_values($events), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(); 