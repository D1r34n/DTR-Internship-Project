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

// ---- VALIDATION ----
if (!$logId || !$employeeId || !$logType || !$newDatetime) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (!in_array($logType, ['IN', 'OUT'])) {
    echo json_encode(['success' => false, 'message' => 'Only Time In and Time Out logs can be edited.']);
    exit();
}

// Optional: force reason (uncomment if needed)
// if ($reason === '') {
//     echo json_encode(['success' => false, 'message' => 'Reason is required.']);
//     exit();
// }

// ---- DEPARTMENT CHECK ----
$myDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$myDept->execute([$myId]);
$myDept = $myDept->fetchColumn();

$empDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$empDept->execute([$employeeId]);
$empDept = $empDept->fetchColumn();

if (!$myDept || $myDept !== $empDept) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

// ---- GET LOG ----
$logRow = $pdo->prepare("SELECT log_time FROM logs WHERE id = ? AND employee_id = ?");
$logRow->execute([$logId, $employeeId]);
$log = $logRow->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
    exit();
}

$workDate = date('Y-m-d', strtotime($log['log_time']));

// ---- GET ATTENDANCE ----
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

// ---- VALIDATE DATETIME ----
$newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
if (!$newDT || $newDT === '1970-01-01 00:00:00') {
    echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
    exit();
}

// ---- PREP VALUES ----
$requestType = ($logType === 'IN') ? 'time_in' : 'time_out';
$reqTimeIn   = ($logType === 'IN') ? $newDT : null;
$reqTimeOut  = ($logType === 'OUT') ? $newDT : null;

// Default reason fallback (prevents empty UI)
if ($reason === '') {
    $reason = 'No reason provided';
}

// ---- CHECK EXISTING PENDING ----
$dupStmt = $pdo->prepare("
    SELECT id FROM log_edit_requests 
    WHERE attendance_id = ? AND status = 'pending'
");
$dupStmt->execute([$attendance['id']]);
$existing = $dupStmt->fetch(PDO::FETCH_ASSOC);

// ---- INSERT OR UPDATE ----
try {
    if ($existing) {
        $updateStmt = $pdo->prepare("
            UPDATE log_edit_requests
            SET request_type       = ?,
                requested_time_in  = ?,
                requested_time_out = ?,
                reason             = ?,
                updated_at         = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $requestType,
            $reqTimeIn,
            $reqTimeOut,
            $reason,
            $existing['id']
        ]);
    } else {
        // FIX: removed actual_time_out — column does not exist in log_edit_requests table
        $insertStmt = $pdo->prepare("
            INSERT INTO log_edit_requests
                (employee_id, attendance_id, work_date, actual_time_in, request_type, requested_time_in, requested_time_out, reason, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $insertStmt->execute([
            $employeeId,
            $attendance['id'],
            $workDate,
            $attendance['actual_time_in'],
            $requestType,
            $reqTimeIn,
            $reqTimeOut,
            $reason
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Edit request submitted for admin review.'
]);