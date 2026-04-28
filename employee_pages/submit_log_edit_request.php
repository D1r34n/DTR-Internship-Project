<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId    = $_SESSION['user_id'];
$attendanceId  = trim($_POST['attendance_id']       ?? '');
$requestedTime = trim($_POST['requested_time_out']  ?? ''); // HH:MM
$reason        = trim($_POST['reason']              ?? '');

if (!$attendanceId || !$requestedTime || !$reason) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if (!preg_match('/^\d{2}:\d{2}$/', $requestedTime)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format.']);
    exit();
}

// Verify attendance record belongs to this employee and is eligible for log edit
$check = $pdo->prepare("
    SELECT id, work_date, actual_time_in, actual_time_out, scheduled_end, undertime_minutes
    FROM attendances
    WHERE id = ? AND employee_id = ? AND actual_time_in IS NOT NULL
    AND (
        actual_time_out IS NULL
        OR (actual_time_out IS NOT NULL AND actual_time_out < scheduled_end)
    )
");
$check->execute([$attendanceId, $employeeId]);
$att = $check->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance record.']);
    exit();
}

$isNoTimeout = is_null($att['actual_time_out']);

// Build requested datetime; add a day if time wraps past midnight (overnight shift)
$requestedDT = $att['work_date'] . ' ' . $requestedTime . ':00';
$anchor      = $isNoTimeout ? $att['actual_time_in'] : $att['actual_time_out'];
if (strtotime($requestedDT) <= strtotime($anchor)) {
    $requestedDT = date('Y-m-d', strtotime($att['work_date'] . ' +1 day')) . ' ' . $requestedTime . ':00';
}

// No-timeout: requested time must not be in the future
// Undertime: allowed to request up to scheduled_end even if it hasn't passed yet
if ($isNoTimeout && strtotime($requestedDT) > time()) {
    echo json_encode(['success' => false, 'message' => 'Requested time out cannot be in the future.']);
    exit();
}

// For undertime: requested time must be after their early time-out
if (!$isNoTimeout && strtotime($requestedDT) <= strtotime($att['actual_time_out'])) {
    echo json_encode(['success' => false, 'message' => 'Requested time out must be after your recorded time out.']);
    exit();
}


// Check for duplicate pending/approved request
$dup = $pdo->prepare("
    SELECT COUNT(*) FROM log_edit_requests
    WHERE attendance_id = ? AND status IN ('pending', 'approved')
");
$dup->execute([$attendanceId]);
if ($dup->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'A log edit request for this date is already pending.']);
    exit();
}

try {
    $pdo->prepare("
        INSERT INTO log_edit_requests
            (employee_id, attendance_id, work_date, actual_time_in, requested_time_out, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([
        $employeeId,
        $attendanceId,
        $att['work_date'],
        $att['actual_time_in'],
        $requestedDT,
        $reason,
    ]);

    echo json_encode(['success' => true, 'message' => 'Log edit request submitted successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
