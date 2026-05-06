<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myId   = $_SESSION['user_id'];
$myRole = $_SESSION['user_role'] ?? '';

// ================================================
// WORKFORCE PATH — editing another employee's log
// ================================================
if ($myRole === 'workforce') {

    $logId       = intval($_POST['log_id']      ?? 0);
    $employeeId  = intval($_POST['employee_id'] ?? 0);
    $logType     = $_POST['log_type']     ?? '';
    $newDatetime = $_POST['new_datetime'] ?? '';
    $reason      = trim($_POST['reason']  ?? '') ?: 'No reason provided';

    if (!$logId || !$employeeId || !$logType || !$newDatetime) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    if (!in_array($logType, ['IN', 'OUT'])) {
        echo json_encode(['success' => false, 'message' => 'Only Time In and Time Out logs can be edited.']);
        exit();
    }

    // Department check
    $myDept  = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
    $myDept->execute([$myId]);
    $myDept  = $myDept->fetchColumn();

    $empDept = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
    $empDept->execute([$employeeId]);
    $empDept = $empDept->fetchColumn();

    if (!$myDept || $myDept !== $empDept) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit();
    }

    // Get log
    $logRow = $pdo->prepare("SELECT log_time FROM logs WHERE id = ? AND employee_id = ?");
    $logRow->execute([$logId, $employeeId]);
    $log = $logRow->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
        exit();
    }

    $workDate = date('Y-m-d', strtotime($log['log_time']));

    // Get attendance
    $attStmt = $pdo->prepare("
        SELECT id, actual_time_in, actual_time_out
        FROM attendances
        WHERE employee_id = ? AND work_date = ?
    ");
    $attStmt->execute([$employeeId, $workDate]);
    $att = $attStmt->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        echo json_encode(['success' => false, 'message' => 'No attendance record found for this date.']);
        exit();
    }

    // Validate datetime
    $newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
    if (!$newDT || $newDT === '1970-01-01 00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
        exit();
    }

    // Future check
    if (strtotime($newDT) > time()) {
        echo json_encode(['success' => false, 'message' => 'Requested time cannot be in the future.']);
        exit();
    }

    $requestType = ($logType === 'IN') ? 'time_in' : 'time_out';
    $reqTimeIn   = ($logType === 'IN')  ? $newDT : null;
    $reqTimeOut  = ($logType === 'OUT') ? $newDT : null;

    // Check existing pending for the same request type
    $dupStmt = $pdo->prepare("SELECT id FROM log_edit_requests WHERE attendance_id = ? AND request_type = ? AND status = 'pending'");
    $dupStmt->execute([$att['id'], $requestType]);
    $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);

    try {
        if ($existing) {
            $pdo->prepare("
                UPDATE log_edit_requests
                SET log_id             = ?,
                    request_type       = ?,
                    requested_time_in  = ?,
                    requested_time_out = ?,
                    reason             = ?,
                    initiated_by_id    = ?,
                    updated_at         = NOW()
                WHERE id = ?
            ")->execute([$logId, $requestType, $reqTimeIn, $reqTimeOut, $reason, $myId, $existing['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO log_edit_requests
                    (employee_id, attendance_id, log_id, work_date, actual_time_in, request_type, requested_time_in, requested_time_out, reason, status, initiated_by_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ")->execute([$employeeId, $att['id'], $logId, $workDate, $att['actual_time_in'], $requestType, $reqTimeIn, $reqTimeOut, $reason, $myId]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Edit request submitted for admin review.']);
    exit();
}

// ================================================
// EMPLOYEE PATH — editing own log
// ================================================
if (in_array($myRole, ['employee', 'admin'])) {

    $employeeId  = $myId;
    $logId       = intval($_POST['log_id']       ?? 0);
    $newDatetime = trim($_POST['new_datetime']   ?? '');
    $reason      = trim($_POST['reason']         ?? '') ?: 'No reason provided';

    if (!$logId || !$newDatetime) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    // Verify employee owns this log
    $logStmt = $pdo->prepare("SELECT log_time, log_type FROM logs WHERE id = ? AND employee_id = ?");
    $logStmt->execute([$logId, $employeeId]);
    $log = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
        exit();
    }

    if (!in_array($log['log_type'], ['IN', 'OUT'])) {
        echo json_encode(['success' => false, 'message' => 'Only Time In and Time Out logs can be edited.']);
        exit();
    }

    $newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
    if (!$newDT || $newDT === '1970-01-01 00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
        exit();
    }

    $workDate    = date('Y-m-d', strtotime($log['log_time']));
    $requestType = ($log['log_type'] === 'IN') ? 'time_in' : 'time_out';

    $attStmt = $pdo->prepare("SELECT id, actual_time_in FROM attendances WHERE employee_id = ? AND work_date = ?");
    $attStmt->execute([$employeeId, $workDate]);
    $att = $attStmt->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        echo json_encode(['success' => false, 'message' => 'No attendance record found for this date.']);
        exit();
    }

    // Check for existing pending request of the same type
    $dup = $pdo->prepare("SELECT id FROM log_edit_requests WHERE attendance_id = ? AND request_type = ? AND status = 'pending'");
    $dup->execute([$att['id'], $requestType]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A pending request for this log entry already exists.']);
        exit();
    }

    try {
        $pdo->prepare("
            INSERT INTO log_edit_requests
                (employee_id, attendance_id, log_id, work_date, actual_time_in, request_type, requested_time_in, requested_time_out, reason, status, initiated_by_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([
            $employeeId,
            $att['id'],
            $logId,
            $workDate,
            $att['actual_time_in'],
            $requestType,
            $requestType === 'time_in'  ? $newDT : null,
            $requestType === 'time_out' ? $newDT : null,
            $reason,
            $employeeId,
        ]);

        echo json_encode(['success' => true, 'message' => 'Log edit request submitted for admin review.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
    exit();
}

// Fallback — role not handled
echo json_encode(['success' => false, 'message' => 'Unauthorized role.']);