<?php
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'workforce') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myId        = $_SESSION['user_id'];
$logId       = intval($_POST['log_id']      ?? 0);
$employeeId  = intval($_POST['employee_id'] ?? 0);
$logType     = $_POST['log_type']     ?? '';
$newDatetime = $_POST['new_datetime'] ?? '';
$reason      = trim($_POST['reason']  ?? '');

if (!$logId || !$employeeId || !$logType || !$newDatetime) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (!in_array($logType, ['IN', 'OUT'])) {
    echo json_encode(['success' => false, 'message' => 'Only Time In and Time Out logs can be edited.']);
    exit();
}

// Verify employee is in same department
$myDeptRow = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$myDeptRow->execute([$myId]);
$myDept = $myDeptRow->fetchColumn();

$empDeptRow = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$empDeptRow->execute([$employeeId]);
$empDept = $empDeptRow->fetchColumn();

if (!$myDept || $myDept !== $empDept) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

// Get the original log to find work_date
$logRow = $pdo->prepare("SELECT log_time FROM logs WHERE id = ? AND employee_id = ?");
$logRow->execute([$logId, $employeeId]);
$log = $logRow->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
    exit();
}

$workDate = date('Y-m-d', strtotime($log['log_time']));

// Find the attendance record for this work date
$attStmt = $pdo->prepare("
    SELECT id, actual_time_in, actual_time_out
    FROM attendances
    WHERE employee_id = ? AND work_date = ?
");
$attStmt->execute([$employeeId, $workDate]);
$attendance = $attStmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    echo json_encode(['success' => false, 'message' => 'No attendance record found for this date.']);
    exit();
}

// Check for existing pending request on this attendance record
$dupStmt = $pdo->prepare("SELECT id FROM log_edit_requests WHERE attendance_id = ? AND status = 'pending'");
$dupStmt->execute([$attendance['id']]);
if ($dupStmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'A pending request already exists for this attendance record.']);
    exit();
}

// Validate new_datetime format
$newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
if (!$newDT || $newDT === '1970-01-01 00:00:00') {
    echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
    exit();
}

$requestType = ($logType === 'IN') ? 'time_in'  : 'time_out';
$reqTimeIn   = ($logType === 'IN') ? $newDT      : null;
$reqTimeOut  = ($logType === 'OUT') ? $newDT     : null;

$insertStmt = $pdo->prepare("
    INSERT INTO log_edit_requests
        (employee_id, attendance_id, actual_time_in, request_type, requested_time_in, requested_time_out, reason, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
");
$insertStmt->execute([
    $employeeId,
    $attendance['id'],
    $attendance['actual_time_in'],
    $requestType,
    $reqTimeIn,
    $reqTimeOut,
    $reason,
]);

echo json_encode(['success' => true, 'message' => 'Log edit request submitted for admin approval.']);
