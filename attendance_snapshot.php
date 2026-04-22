<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$employeeId = $_SESSION['user_id'];
$today = date('Y-m-d');

/* =========================
   1. FETCH RAW LOGS (SOURCE OF TRUTH)
========================= */
$stmt = $pdo->prepare("
    SELECT log_type, log_time
    FROM logs
    WHERE employee_id = ?
      AND DATE(log_time) = ?
    ORDER BY log_time ASC
");
$stmt->execute([$employeeId, $today]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   2. PAIR LOGS INTO SESSION
========================= */
$inTime = null;
$outTime = null;
$lastLogType = null;

foreach ($logs as $log) {
    if ($log['log_type'] === 'IN') {
        $inTime = $inTime ?? $log['log_time']; // first IN
        $lastLogType = 'IN';
    }

    if ($log['log_type'] === 'OUT') {
        $outTime = $log['log_time']; // always overwrite to last OUT
        $lastLogType = 'OUT';
    }
}

/* =========================
   3. GET SCHEDULE (OPTIONAL BUT IMPORTANT)
========================= */
$schedStmt = $pdo->prepare("
    SELECT scheduled_start_datetime, scheduled_end_datetime
    FROM schedules
    WHERE employee_id = ?
    AND schedule_date = ?
    AND is_rest_day = 0
    LIMIT 1
");
$schedStmt->execute([$employeeId, $today]);
$schedule = $schedStmt->fetch(PDO::FETCH_ASSOC);

$scheduledIn  = $schedule['scheduled_start_datetime'] ?? null;
$scheduledOut = $schedule['scheduled_end_datetime']   ?? null;

/* =========================
   4. COMPUTE METRICS
========================= */
$lateMinutes = 0;
$undertimeMinutes = 0;
$totalSeconds = 0;
$status = 'present';

$inTs  = $inTime  ? strtotime($inTime)  : null;
$outTs = $outTime ? strtotime($outTime) : null;

$schedInTs  = $scheduledIn  ? strtotime($scheduledIn)  : null;
$schedOutTs = $scheduledOut ? strtotime($scheduledOut) : null;

/* LATE */
if ($inTs && $schedInTs && $inTs > $schedInTs) {
    $lateMinutes = floor(($inTs - $schedInTs) / 60);
}

/* UNDERTIME */
if ($outTs && $schedOutTs && $outTs < $schedOutTs) {
    $undertimeMinutes = floor(($schedOutTs - $outTs) / 60);
}

/* TOTAL WORK HOURS */
if ($inTs && $outTs) {
    $totalSeconds = $outTs - $inTs;
}

/* STATUS RULE (your simplified logic) */
if (!$inTs || !$outTs) {
    $status = 'incomplete';
} elseif ($lateMinutes > 0) {
    $status = 'late';
} elseif ($undertimeMinutes > 0) {
    $status = 'undertime';
} else {
    $status = 'present';
}

/* =========================
   5. TOPBAR STATE
========================= */
$topbarState = ($lastLogType === 'IN') ? 'timed_in' : 'timed_out';

/* =========================
   6. TABLE DATA (UI READY)
========================= */
$table = [
    [
        'date' => $today,
        'time_in' => $inTime,
        'time_out' => $outTime,
        'late_minutes' => $lateMinutes,
        'undertime_minutes' => $undertimeMinutes,
        'total_hours' => $totalSeconds ? round($totalSeconds / 3600, 2) : 0,
        'status' => $status
    ]
];

/* =========================
   7. GANTT DATA (UI READY)
========================= */
$gantt = [];

if ($inTs && $outTs && $schedInTs && $schedOutTs) {
    $gantt = [
        'date' => $today,
        'range_start' => $schedInTs - 7200,
        'range_end' => $outTs + 7200,

        'scheduled_in' => date('h:i A', $schedInTs),
        'scheduled_out' => date('h:i A', $schedOutTs),
        'actual_in' => date('h:i A', $inTs),
        'actual_out' => date('h:i A', $outTs),

        'late_minutes' => $lateMinutes,
        'undertime_minutes' => $undertimeMinutes
    ];
}

/* =========================
   8. FINAL RESPONSE (SINGLE SOURCE OF TRUTH)
========================= */
echo json_encode([
    'topbar' => [
        'state' => $topbarState
    ],
    'today' => [
        'status' => $status,
        'late_minutes' => $lateMinutes,
        'undertime_minutes' => $undertimeMinutes,
        'total_hours' => $totalSeconds ? round($totalSeconds / 3600, 2) : 0
    ],
    'table' => $table,
    'gantt' => $gantt
]);