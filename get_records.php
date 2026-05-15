<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

require_once 'db.php';
require_once 'system_functions/system_library.php';
require_once 'system_functions/system_service.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$employeeId = (int) $_SESSION['user_id'];

$rawMonth = $_GET['month'] ?? date('Y-m');
[$yr, $mn] = array_pad(array_map('intval', explode('-', $rawMonth)), 2, 0);
if ($yr < 2000 || $mn < 1 || $mn > 12) { $yr = (int)date('Y'); $mn = (int)date('n'); }
$startDate  = sprintf('%04d-%02d-01', $yr, $mn);
$endDate    = date('Y-m-t', strtotime($startDate));
$monthLabel = date('F Y', strtotime($startDate));

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
    WHERE employee_id = ? AND leave_type = 'ob leave' AND start_date BETWEEN ? AND ?
");
$obStmt->execute([$employeeId, $startDate, $endDate]);
$obMap = [];
foreach ($obStmt->fetchAll(PDO::FETCH_ASSOC) as $ob) {
    $obMap[$ob['ob_date']] = $ob['status'];
}

$rows = [];
foreach ($records as $row) {
    $sched    = $schedules[$row['work_date']] ?? null;
    $ganttBar = computeGanttRow($row, $sched);
    if ($ganttBar === null) continue;

    $leaveStatus = $leaveMap[$row['work_date']] ?? null;
    $obStatus    = $obMap[$row['work_date']]    ?? null;

    if ($leaveStatus === 'approved') {
        $ganttBar['type']       = 'absent_or_future';
        $ganttBar['barClass']   = 'ganttBarLeave';
        $ganttBar['labelClass'] = 'ganttAbsentLabel';
        $ganttBar['labelText']  = 'On Leave';
        $ganttBar['barLeft']    = 0;
        $ganttBar['barWidth']   = 100;
        $ganttBar['midLeft']    = 50;
    } elseif ($obStatus === 'approved') {
        $ganttBar['type']       = 'absent_or_future';
        $ganttBar['barClass']   = 'ganttBarOB';
        $ganttBar['labelClass'] = 'ganttAbsentLabel';
        $ganttBar['labelText']  = 'On OB';
        $ganttBar['barLeft']    = 0;
        $ganttBar['barWidth']   = 100;
        $ganttBar['midLeft']    = 50;
    }

    $rows[] = $ganttBar;
}

echo json_encode([
    'meta' => [
        'monthLabel'   => $monthLabel,
        'presentCount' => $presentCount,
        'absentCount'  => $absentCount,
        'pendingCount' => $pendingCount,
    ],
    'rows' => $rows,
]);
