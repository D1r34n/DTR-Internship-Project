<?php
session_start();
if (!isset($_SESSION['user_id']) || !<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

$employeeId = intval($_GET['employee_id'] ?? 0);
if (!$employeeId) { echo '[]'; exit(); }

// FullCalendar passes start/end as ISO datetime strings; end is exclusive
$firstDay = date('Y-m-d', strtotime($_GET['start'] ?? date('Y-m-01')));
$lastDay  = date('Y-m-d', strtotime(($_GET['end'] ?? date('Y-m-t')) . ' -1 day'));

// ---- Schedules ----
$stmt = $pdo->prepare("
    SELECT schedule_date, scheduled_start, scheduled_end, is_rest_day, status
    FROM schedules
    WHERE employee_id = ? AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date
");
$stmt->execute([$employeeId, $firstDay, $lastDay]);
$schedMap = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schedMap[$row['schedule_date']] = $row;
}

// ---- Leave requests (non-OB) ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND leave_type != 'ob leave'
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

// ---- OB requests ----
$obStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ? AND leave_type = 'ob leave'
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

// ---- Night continuation dates ----
$nightContDates = [];
foreach ($schedMap as $date => $sched) {
    if ($sched['is_rest_day'] || !$sched['scheduled_start'] || !$sched['scheduled_end']) continue;
    $endDate = date('Y-m-d', strtotime($sched['scheduled_end']));
    if ($endDate > $date && $endDate <= $lastDay) {
        $nightContDates[$endDate] = $date; // maps cont-date → origin-date
    }
}

$events = [];

// Collect all dates with any content
$allDates = array_unique(array_merge(
    array_keys($schedMap),
    array_keys($leaveMap),
    array_keys($obMap),
    array_keys($nightContDates)
));
sort($allDates);

foreach ($allDates as $dateStr) {
    $sched       = $schedMap[$dateStr] ?? null;
    $leaveStatus = $leaveMap[$dateStr] ?? null;
    $obStatus    = $obMap[$dateStr]    ?? null;
    $isNightCont = isset($nightContDates[$dateStr]);

    // Night continuation event (separate, shown first in the cell)
    if ($isNightCont) {
        $originDate  = $nightContDates[$dateStr];
        $originSched = $schedMap[$originDate] ?? null;
        $contTimeStr = $originSched ? ('until ' . date('g:i A', strtotime($originSched['scheduled_end']))) : 'Night (cont.)';
        $events[] = [
            'id'         => 'night-cont-' . $dateStr,
            'title'      => $contTimeStr,
            'start'      => $dateStr,
            'allDay'     => true,
            'classNames' => ['fc-ev-night-cont'],
            'extendedProps' => [
                'type'              => 'night-cont',
                'dateStr'           => $dateStr,
                'hasSchedule'       => false,
                'hasActiveLeaveOrOB'=> false,
                'timeInStr'         => null,
                'timeOutStr'        => null,
                'schedInVal'        => null,
                'schedOutVal'       => null,
                'isRestDay'         => false,
            ],
        ];
    }

    // Determine the main event for this day (same priority logic as before)
    $hasActiveLeaveOrOB  = in_array($leaveStatus, ['approved', 'pending']) || in_array($obStatus, ['approved', 'pending']);
    $isRejectedLeaveOrOB = false;
    $eventType  = null;
    $eventTitle = null;
    $timeInStr  = null;
    $timeOutStr = null;
    $schedInVal = null;
    $schedOutVal = null;
    $isRestDay  = false;

    if ($leaveStatus === 'approved') {
        $eventType  = 'on-leave';
        $eventTitle = 'On Leave';
    } elseif ($obStatus === 'approved') {
        $eventType  = 'on-ob';
        $eventTitle = 'On OB';
    } elseif ($leaveStatus === 'pending') {
        $eventType  = 'leave-pending';
        $eventTitle = 'Leave Pending';
    } elseif ($obStatus === 'pending') {
        $eventType  = 'ob-pending';
        $eventTitle = 'OB Pending';
    } elseif ($leaveStatus === 'rejected') {
        $eventType           = 'leave-rejected';
        $eventTitle          = 'Leave Rejected';
        $isRejectedLeaveOrOB = true;
    } elseif ($obStatus === 'rejected') {
        $eventType           = 'leave-rejected';
        $eventTitle          = 'OB Rejected';
        $isRejectedLeaveOrOB = true;
    } elseif ($sched && $sched['status'] === 'pending') {
        $eventType = 'pending-schedule';
        $eventTitle = 'Pending Schedule';
        $isRestDay  = (bool)$sched['is_rest_day'];
        if (!$sched['is_rest_day'] && $sched['scheduled_start']) {
            $endTs       = strtotime($sched['scheduled_end']);
            $isOvernight = date('Y-m-d', $endTs) > $dateStr;
            $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
            $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
            $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
            $schedOutVal = date('H:i', $endTs);
        }
    } elseif ($sched && $sched['is_rest_day']) {
        $eventType  = 'rest';
        $eventTitle = 'Rest Day';
        $isRestDay  = true;
    } elseif ($sched) {
        $endTs       = strtotime($sched['scheduled_end']);
        $isOvernight = date('Y-m-d', $endTs) > $dateStr;
        $sh          = (int) date('H', strtotime($sched['scheduled_start']));
        $eventType   = ($sh >= 18 || $sh < 6) ? 'night' : 'day';
        $eventTitle  = ($sh >= 18 || $sh < 6) ? 'Night Shift' : 'Day Shift';
        $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
        $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
        $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
        $schedOutVal = date('H:i', $endTs);
    }

    // For rejected leave/OB, also surface the underlying shift times if a schedule exists
    if ($isRejectedLeaveOrOB && $sched && !$sched['is_rest_day']) {
        $endTs       = strtotime($sched['scheduled_end']);
        $isOvernight = date('Y-m-d', $endTs) > $dateStr;
        $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
        $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
        $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
        $schedOutVal = date('H:i', $endTs);
    }

    if ($eventType === null) continue;

    $events[] = [
        'id'         => 'main-' . $dateStr,
        'title'      => $eventTitle,
        'start'      => $dateStr,
        'allDay'     => true,
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

echo json_encode($events);
SESSION['user_role'] === 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

$employeeId = intval($_GET['employee_id'] ?? 0);
if (!$employeeId) { echo '[]'; exit(); }

// FullCalendar passes start/end as ISO datetime strings; end is exclusive
$firstDay = date('Y-m-d', strtotime($_GET['start'] ?? date('Y-m-01')));
$lastDay  = date('Y-m-d', strtotime(($_GET['end'] ?? date('Y-m-t')) . ' -1 day'));

// ---- Schedules ----
$stmt = $pdo->prepare("
    SELECT schedule_date, scheduled_start, scheduled_end, is_rest_day, status
    FROM schedules
    WHERE employee_id = ? AND schedule_date BETWEEN ? AND ?
    ORDER BY schedule_date
");
$stmt->execute([$employeeId, $firstDay, $lastDay]);
$schedMap = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schedMap[$row['schedule_date']] = $row;
}

// ---- Leave requests (non-OB) ----
$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND leave_type != 'ob leave'
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

// ---- OB requests ----
$obStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ? AND leave_type = 'ob leave'
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

// ---- Night continuation dates ----
$nightContDates = [];
foreach ($schedMap as $date => $sched) {
    if ($sched['is_rest_day'] || !$sched['scheduled_start'] || !$sched['scheduled_end']) continue;
    $endDate = date('Y-m-d', strtotime($sched['scheduled_end']));
    if ($endDate > $date && $endDate <= $lastDay) {
        $nightContDates[$endDate] = $date; // maps cont-date → origin-date
    }
}

$events = [];

// Collect all dates with any content
$allDates = array_unique(array_merge(
    array_keys($schedMap),
    array_keys($leaveMap),
    array_keys($obMap),
    array_keys($nightContDates)
));
sort($allDates);

foreach ($allDates as $dateStr) {
    $sched       = $schedMap[$dateStr] ?? null;
    $leaveStatus = $leaveMap[$dateStr] ?? null;
    $obStatus    = $obMap[$dateStr]    ?? null;
    $isNightCont = isset($nightContDates[$dateStr]);

    // Night continuation event (separate, shown first in the cell)
    if ($isNightCont) {
        $originDate  = $nightContDates[$dateStr];
        $originSched = $schedMap[$originDate] ?? null;
        $contTimeStr = $originSched ? ('until ' . date('g:i A', strtotime($originSched['scheduled_end']))) : 'Night (cont.)';
        $events[] = [
            'id'         => 'night-cont-' . $dateStr,
            'title'      => $contTimeStr,
            'start'      => $dateStr,
            'allDay'     => true,
            'classNames' => ['fc-ev-night-cont'],
            'extendedProps' => [
                'type'              => 'night-cont',
                'dateStr'           => $dateStr,
                'hasSchedule'       => false,
                'hasActiveLeaveOrOB'=> false,
                'timeInStr'         => null,
                'timeOutStr'        => null,
                'schedInVal'        => null,
                'schedOutVal'       => null,
                'isRestDay'         => false,
            ],
        ];
    }

    // Determine the main event for this day (same priority logic as before)
    $hasActiveLeaveOrOB  = in_array($leaveStatus, ['approved', 'pending']) || in_array($obStatus, ['approved', 'pending']);
    $isRejectedLeaveOrOB = false;
    $eventType  = null;
    $eventTitle = null;
    $timeInStr  = null;
    $timeOutStr = null;
    $schedInVal = null;
    $schedOutVal = null;
    $isRestDay  = false;

    if ($leaveStatus === 'approved') {
        $eventType  = 'on-leave';
        $eventTitle = 'On Leave';
    } elseif ($obStatus === 'approved') {
        $eventType  = 'on-ob';
        $eventTitle = 'On OB';
    } elseif ($leaveStatus === 'pending') {
        $eventType  = 'leave-pending';
        $eventTitle = 'Leave Pending';
    } elseif ($obStatus === 'pending') {
        $eventType  = 'ob-pending';
        $eventTitle = 'OB Pending';
    } elseif ($leaveStatus === 'rejected') {
        $eventType           = 'leave-rejected';
        $eventTitle          = 'Leave Rejected';
        $isRejectedLeaveOrOB = true;
    } elseif ($obStatus === 'rejected') {
        $eventType           = 'leave-rejected';
        $eventTitle          = 'OB Rejected';
        $isRejectedLeaveOrOB = true;
    } elseif ($sched && $sched['status'] === 'pending') {
        $eventType = 'pending-schedule';
        $eventTitle = 'Pending Schedule';
        $isRestDay  = (bool)$sched['is_rest_day'];
        if (!$sched['is_rest_day'] && $sched['scheduled_start']) {
            $endTs       = strtotime($sched['scheduled_end']);
            $isOvernight = date('Y-m-d', $endTs) > $dateStr;
            $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
            $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
            $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
            $schedOutVal = date('H:i', $endTs);
        }
    } elseif ($sched && $sched['is_rest_day']) {
        $eventType  = 'rest';
        $eventTitle = 'Rest Day';
        $isRestDay  = true;
    } elseif ($sched) {
        $endTs       = strtotime($sched['scheduled_end']);
        $isOvernight = date('Y-m-d', $endTs) > $dateStr;
        $sh          = (int) date('H', strtotime($sched['scheduled_start']));
        $eventType   = ($sh >= 18 || $sh < 6) ? 'night' : 'day';
        $eventTitle  = ($sh >= 18 || $sh < 6) ? 'Night Shift' : 'Day Shift';
        $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
        $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
        $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
        $schedOutVal = date('H:i', $endTs);
    }

    // For rejected leave/OB, also surface the underlying shift times if a schedule exists
    if ($isRejectedLeaveOrOB && $sched && !$sched['is_rest_day']) {
        $endTs       = strtotime($sched['scheduled_end']);
        $isOvernight = date('Y-m-d', $endTs) > $dateStr;
        $timeInStr   = date('g:i A', strtotime($sched['scheduled_start']));
        $timeOutStr  = date('g:i A', $endTs) . ($isOvernight ? ' ↪' : '');
        $schedInVal  = date('H:i', strtotime($sched['scheduled_start']));
        $schedOutVal = date('H:i', $endTs);
    }

    if ($eventType === null) continue;

    $events[] = [
        'id'         => 'main-' . $dateStr,
        'title'      => $eventTitle,
        'start'      => $dateStr,
        'allDay'     => true,
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

echo json_encode($events);
