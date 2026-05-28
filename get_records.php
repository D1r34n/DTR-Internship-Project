<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

require_once 'db.php';
require_once 'system_functions/system_service.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$isAdmin    = $_SESSION['user_role'] === 'superadmin';
$requested  = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$employeeId = ($isAdmin && $requested > 0) ? $requested : (int)$_SESSION['user_id'];

if (!empty($_GET['start']) && !empty($_GET['end'])) {
    $startDate  = date('Y-m-d', strtotime($_GET['start']));
    $endDate    = date('Y-m-d', strtotime($_GET['end']));
    if ($startDate > $endDate) $endDate = $startDate;
    $monthLabel = date('M j', strtotime($startDate)) . ' – ' . date('M j, Y', strtotime($endDate));
} else {
    $rawMonth = $_GET['month'] ?? date('Y-m');
    [$yr, $mn] = array_pad(array_map('intval', explode('-', $rawMonth)), 2, 0);
    if ($yr < 2000 || $mn < 1 || $mn > 12) { $yr = (int)date('Y'); $mn = (int)date('n'); }
    $startDate  = sprintf('%04d-%02d-01', $yr, $mn);
    $endDate    = date('Y-m-t', strtotime($startDate));
    $monthLabel = date('F Y', strtotime($startDate));
}

$records   = getAttendanceRecords($pdo, $employeeId, $startDate, $endDate);
$schedules = getSchedulesByDateRange($pdo, $employeeId, $startDate, $endDate);

$presentCount = $absentCount = $pendingCount = 0;
foreach ($records as $r) {
    if ($r['status'] === 'present')    $presentCount++;
    elseif ($r['status'] === 'absent') $absentCount++;
    else                               $pendingCount++;
}

$leaveStmt = $pdo->prepare("
    SELECT start_date, end_date, selected_dates, status
    FROM leave_requests
    WHERE employee_id = ?
    AND leave_type_id != (SELECT id FROM leave_types WHERE name = 'ob leave')
    AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ? OR (start_date <= ? AND end_date >= ?))
");
$leaveStmt->execute([$employeeId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
$leaveMap = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
    $dates = json_decode($leave['selected_dates'], true);
    if (is_array($dates) && !empty($dates)) {
        foreach ($dates as $d) {
            if ($d >= $startDate && $d <= $endDate) $leaveMap[$d] = $leave['status'];
        }
    } else {
        $cur  = new DateTime($leave['start_date']);
        $lEnd = new DateTime($leave['end_date']);
        while ($cur <= $lEnd) {
            $d = $cur->format('Y-m-d');
            if ($d >= $startDate && $d <= $endDate) $leaveMap[$d] = $leave['status'];
            $cur->modify('+1 day');
        }
    }
}

$obStmt = $pdo->prepare("
    SELECT start_date AS ob_date, status FROM leave_requests
    WHERE employee_id = ? AND leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave') AND start_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $startDate, $endDate]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}

$recordsOut = array_map(fn($r) => [
    'work_date'         => $r['work_date'],
    'scheduled_start'   => $r['scheduled_start'],
    'scheduled_end'     => $r['scheduled_end'],
    'actual_time_in'    => $r['actual_time_in'],
    'actual_time_out'   => $r['actual_time_out'],
    'status'            => $r['status'],
    'late_minutes'      => (int)$r['late_minutes'],
    'undertime_minutes' => (int)$r['undertime_minutes'],
    'overtime_minutes'  => (int)$r['overtime_minutes'],
    'break_minutes'     => (int)$r['break_minutes'],
    'overtime_status'   => $r['overtime_status'],
    'missed_time_out'   => (int)$r['missed_time_out'],
    'first_break_in'    => $r['first_break_in'],
    'last_break_out'    => $r['last_break_out'],
], $records);

// Inject upcoming scheduled days that have no attendance record yet
$today          = date('Y-m-d');
$existingDates  = array_column($recordsOut, 'work_date');
foreach ($schedules as $date => $sched) {
    if ($date > $today && !in_array($date, $existingDates)) {
        $recordsOut[] = [
            'work_date'         => $date,
            'scheduled_start'   => $sched['scheduled_start'],
            'scheduled_end'     => $sched['scheduled_end'],
            'actual_time_in'    => null,
            'actual_time_out'   => null,
            'status'            => 'upcoming',
            'late_minutes'      => 0,
            'undertime_minutes' => 0,
            'overtime_minutes'  => 0,
            'break_minutes'     => 0,
            'overtime_status'   => null,
            'missed_time_out'   => 0,
            'first_break_in'    => null,
            'last_break_out'    => null,
        ];
    }
}
usort($recordsOut, fn($a, $b) => strcmp($b['work_date'], $a['work_date']));

echo json_encode([
    'meta' => [
        'monthLabel'   => $monthLabel,
        'presentCount' => $presentCount,
        'absentCount'  => $absentCount,
        'pendingCount' => $pendingCount,
    ],
    'records'   => $recordsOut,
    'schedules' => $schedules,
    'leaveMap'  => $leaveMap,
    'obMap'     => $obMap,
]);
