<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once 'db.php';
date_default_timezone_set('Asia/Manila');

$empId    = (int)$_SESSION['user_id'];
$today    = date('Y-m-d');
$todayDow = (int)date('N'); // 1=Mon, 7=Sun
$weekMon  = date('Y-m-d', strtotime('-' . ($todayDow - 1) . ' days'));
$weekSun  = date('Y-m-d', strtotime('+' . (7 - $todayDow) . ' days'));

// Schedules
$s = $pdo->prepare("
    SELECT s.schedule_date, s.is_rest_day, s.scheduled_start
    FROM schedules s
    WHERE s.employee_id = ?
      AND s.schedule_date BETWEEN ? AND ?
      AND (
          s.batch_id IS NULL
          OR NOT EXISTS (SELECT 1 FROM schedule_edit_requests ser WHERE ser.batch_id = s.batch_id)
          OR EXISTS (SELECT 1 FROM schedule_edit_requests ser WHERE ser.batch_id = s.batch_id AND ser.status = 'approved')
      )
");
$s->execute([$empId, $weekMon, $weekSun]);
$empSchedMap = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $empSchedMap[$r['schedule_date']] = $r;
}

// Attendances
$s = $pdo->prepare("
    SELECT work_date, status, late_minutes FROM attendances
    WHERE employee_id = ? AND work_date BETWEEN ? AND ?
");
$s->execute([$empId, $weekMon, $weekSun]);
$empAttMap  = [];
$empLateMap = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $empAttMap[$r['work_date']]  = $r['status'];
    $empLateMap[$r['work_date']] = (int)$r['late_minutes'];
}

// Approved leaves
$s = $pdo->prepare("
    SELECT selected_dates, start_date, end_date
    FROM leave_requests
    WHERE employee_id = ? AND leave_type_id != (SELECT id FROM leave_types WHERE name = 'ob leave') AND status = 'approved'
      AND (start_date BETWEEN ? AND ? OR end_date BETWEEN ? AND ?
           OR (start_date <= ? AND end_date >= ?))
");
$s->execute([$empId, $weekMon, $weekSun, $weekMon, $weekSun, $weekMon, $weekSun]);
$empLeaveSet = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $dates = json_decode($r['selected_dates'], true);
    if (is_array($dates) && !empty($dates)) {
        foreach ($dates as $d) {
            if ($d >= $weekMon && $d <= $weekSun) $empLeaveSet[$d] = true;
        }
    } else {
        $cur = new DateTime($r['start_date']);
        $fin = new DateTime($r['end_date']);
        while ($cur <= $fin) {
            $d = $cur->format('Y-m-d');
            if ($d >= $weekMon && $d <= $weekSun) $empLeaveSet[$d] = true;
            $cur->modify('+1 day');
        }
    }
}

// OB (approved)
$s = $pdo->prepare("
    SELECT start_date FROM leave_requests
    WHERE employee_id = ? AND leave_type_id = (SELECT id FROM leave_types WHERE name = 'ob leave') AND status = 'approved'
      AND start_date BETWEEN ? AND ?
");
$s->execute([$empId, $weekMon, $weekSun]);
$empOBSet = [];
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $empOBSet[$r['start_date']] = true;
}

// Build days
$days = [];
for ($i = 0; $i < 7; $i++) {
    $date  = date('Y-m-d', strtotime($weekMon . " +$i days"));
    $sched = $empSchedMap[$date] ?? null;

    if (!$sched) {
        $status = 'none';
    } elseif ($sched['is_rest_day']) {
        $status = 'rest';
    } elseif (isset($empOBSet[$date])) {
        $status = 'ob';
    } elseif (isset($empLeaveSet[$date])) {
        $status = 'leave';
    } elseif (in_array(strtolower($empAttMap[$date] ?? ''), ['present', 'undertime', 'overtime', 'incomplete'])) {
        $status = ($empLateMap[$date] ?? 0) > 0 ? 'late' : 'present';
    } elseif ($date < $today) {
        $status = 'absent';
    } elseif ($date === $today && $sched['scheduled_start'] && time() >= strtotime($sched['scheduled_start'])) {
        $status = 'absent';
    } else {
        $status = 'upcoming';
    }

    $days[] = ['date' => $date, 'status' => $status];
}

header('Content-Type: application/json');
echo json_encode(['days' => $days]);
