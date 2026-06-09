<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager', 'workforce'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

$start      = $_GET['start'] ?? date('Y-m-01');
$end        = $_GET['end']   ?? date('Y-m-t');
$employeeId = (int) $_SESSION['user_id'];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Day shifts (scheduled_start hour 6–17) — scoped to the current user
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM schedules
    WHERE is_rest_day = 0
    AND COALESCE(is_archived, 0) = 0
    AND pending_delete = 0
    AND employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    AND scheduled_start IS NOT NULL
    AND HOUR(scheduled_start) >= 6 AND HOUR(scheduled_start) < 18
");
$stmt->execute([$employeeId, $start, $end]);
$day = (int) $stmt->fetchColumn();

// Night shifts (scheduled_start hour 18–23 or 0–5) — scoped to the current user
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM schedules
    WHERE is_rest_day = 0
    AND COALESCE(is_archived, 0) = 0
    AND pending_delete = 0
    AND employee_id = ?
    AND schedule_date BETWEEN ? AND ?
    AND scheduled_start IS NOT NULL
    AND (HOUR(scheduled_start) >= 18 OR HOUR(scheduled_start) < 6)
");
$stmt->execute([$employeeId, $start, $end]);
$night = (int) $stmt->fetchColumn();

// Rest days — scoped to the current user
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM schedules
    WHERE is_rest_day = 1
    AND COALESCE(is_archived, 0) = 0
    AND pending_delete = 0
    AND employee_id = ?
    AND schedule_date BETWEEN ? AND ?
");
$stmt->execute([$employeeId, $start, $end]);
$rest = (int) $stmt->fetchColumn();

// On Leave — count individual approved leave-days falling in range
$stmt = $pdo->prepare("
    SELECT lr.selected_dates
    FROM leave_requests lr
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.status = 'approved'
    AND lt.name != 'ob leave'
    AND lr.start_date <= ? AND lr.end_date >= ?
");
$stmt->execute([$end, $start]);
$leave = 0;
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
    foreach ((json_decode($json, true) ?? []) as $d) {
        if ($d >= $start && $d <= $end) $leave++;
    }
}

// On OB — count individual approved OB-days falling in range
$stmt = $pdo->prepare("
    SELECT lr.selected_dates
    FROM leave_requests lr
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.status = 'approved'
    AND lt.name = 'ob leave'
    AND lr.start_date <= ? AND lr.end_date >= ?
");
$stmt->execute([$end, $start]);
$ob = 0;
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
    foreach ((json_decode($json, true) ?? []) as $d) {
        if ($d >= $start && $d <= $end) $ob++;
    }
}

echo json_encode(compact('day', 'night', 'rest', 'leave', 'ob'));
