<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId  = $_SESSION['user_id'];
$attendanceId = trim($_POST['attendance_id'] ?? '');
$requestType  = trim($_POST['request_type']  ?? ''); // 'time_in' | 'time_out' | 'both'
$reqTimeIn    = trim($_POST['requested_time_in']  ?? '');
$reqTimeOut   = trim($_POST['requested_time_out'] ?? '');
$reason       = trim($_POST['reason'] ?? '');

if (!$attendanceId || !$requestType || !$reason) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if (!in_array($requestType, ['time_in', 'time_out', 'both'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request type.']);
    exit();
}

if (($requestType === 'time_in' || $requestType === 'both') && !preg_match('/^\d{2}:\d{2}$/', $reqTimeIn)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time in format.']);
    exit();
}

if (($requestType === 'time_out' || $requestType === 'both') && !preg_match('/^\d{2}:\d{2}$/', $reqTimeOut)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time out format.']);
    exit();
}

// Verify attendance record belongs to this employee and is eligible
$check = $pdo->prepare("
    SELECT id, work_date, actual_time_in, actual_time_out, scheduled_start, scheduled_end
    FROM attendances
    WHERE id = ? AND employee_id = ? AND actual_time_in IS NOT NULL
    AND (
        (actual_time_out IS NULL AND scheduled_end < NOW())
        OR (actual_time_out IS NOT NULL AND actual_time_out < scheduled_end)
        OR (actual_time_in > scheduled_start)
    )
");
$check->execute([$attendanceId, $employeeId]);
$att = $check->fetch(PDO::FETCH_ASSOC);

if (!$att) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance record.']);
    exit();
}

// Build and validate requested_time_in
$reqTimeInDT = null;
if ($requestType === 'time_in' || $requestType === 'both') {
    $reqTimeInDT = $att['work_date'] . ' ' . $reqTimeIn . ':00';
    if (strtotime($reqTimeInDT) >= strtotime($att['actual_time_in'])) {
        echo json_encode(['success' => false, 'message' => 'Requested time in must be earlier than your actual time in.']);
        exit();
    }
    if (strtotime($reqTimeInDT) > time()) {
        echo json_encode(['success' => false, 'message' => 'Requested time in cannot be in the future.']);
        exit();
    }
}

// Build and validate requested_time_out
$reqTimeOutDT = null;
if ($requestType === 'time_out' || $requestType === 'both') {
    $anchorIn     = $reqTimeInDT ?? $att['actual_time_in'];
    $reqTimeOutDT = $att['work_date'] . ' ' . $reqTimeOut . ':00';
    // Handle overnight wrap
    if (strtotime($reqTimeOutDT) <= strtotime($anchorIn)) {
        $reqTimeOutDT = date('Y-m-d', strtotime($att['work_date'] . ' +1 day')) . ' ' . $reqTimeOut . ':00';
    }
    $isNoTimeout = is_null($att['actual_time_out']);
    if ($isNoTimeout && strtotime($reqTimeOutDT) > time()) {
        echo json_encode(['success' => false, 'message' => 'Requested time out cannot be in the future.']);
        exit();
    }
    // Undertime-only correction: new time out must be after the recorded one
    if (!$isNoTimeout && $requestType === 'time_out' && strtotime($reqTimeOutDT) <= strtotime($att['actual_time_out'])) {
        echo json_encode(['success' => false, 'message' => 'Requested time out must be after your recorded time out.']);
        exit();
    }
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
            (employee_id, attendance_id, work_date, actual_time_in, request_type, requested_time_in, requested_time_out, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([
        $employeeId,
        $attendanceId,
        $att['work_date'],
        $att['actual_time_in'],
        $requestType,
        $reqTimeInDT,
        $reqTimeOutDT,
        $reason,
    ]);

    echo json_encode(['success' => true, 'message' => 'Log edit request submitted successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
