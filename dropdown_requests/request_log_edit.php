<?php
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require_once '../db.php';
require_once __DIR__ . '/../system_functions/system_service.php';
date_default_timezone_set('Asia/Manila');

$myId   = $_SESSION['user_id'];
$myRole = $_SESSION['user_role'] ?? '';

/**
 * CHECK IF LOG WAS ALREADY APPROVED/EDITED
 */
function hasApprovedLogEdit($pdo, $logId) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM log_edit_requests 
        WHERE log_id = ? 
          AND status = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$logId]);
    return (bool) $stmt->fetch();
}

// ================================================
// ADMIN PATH — directly applies edit to any employee's log
// ================================================
if ($myRole === 'superadmin') {

    $employeeId  = intval($_POST['employee_id'] ?? $myId);
    $logId       = intval($_POST['log_id']       ?? 0);
    $newDatetime = trim($_POST['new_datetime']   ?? '');
    $reason      = trim($_POST['reason']         ?? '') ?: 'No reason provided';

    if (!$logId || !$newDatetime) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    $logStmt = $pdo->prepare("SELECT log_time, log_type FROM logs WHERE id = ? AND employee_id = ?");
    $logStmt->execute([$logId, $employeeId]);
    $log = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
        exit();
    }

    // ❌ BLOCK IF ALREADY EDITED
    if (hasApprovedLogEdit($pdo, $logId)) {
        echo json_encode([
            'success' => false,
            'message' => 'This log has already been edited.'
        ]);
        exit();
    }

    $newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
    if (!$newDT || $newDT === '1970-01-01 00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
        exit();
    }

    $workDate    = date('Y-m-d', strtotime($log['log_time']));
    $requestType = match($log['log_type']) {
        'IN'        => 'time_in',
        'OUT'       => 'time_out',
        'BREAK_IN'  => 'break_in',
        'BREAK_OUT' => 'break_out',
        default     => strtolower($log['log_type']),
    };

    $attStmt = $pdo->prepare("SELECT id, actual_time_in FROM attendances WHERE employee_id = ? AND work_date = ?");
    $attStmt->execute([$employeeId, $workDate]);
    $att = $attStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $reqTimeIn  = ($requestType === 'time_in')  ? $newDT : null;
    $reqTimeOut = ($requestType === 'time_out') ? $newDT : null;

    try {
        $pdo->beginTransaction();

        // 1. Update log row
        $pdo->prepare("UPDATE logs SET log_time = ? WHERE id = ?")
            ->execute([$newDT, $logId]);

        // 2. Insert audit record (approved)
        $pdo->prepare("
            INSERT INTO log_edit_requests
                (employee_id, attendance_id, log_id, work_date,
                 request_type, requested_time_in, requested_time_out, reason, status, initiated_by_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
        ")->execute([
            $employeeId, $att['id'] ?? null, $logId, $workDate,
            $requestType, $reqTimeIn, $reqTimeOut, $reason, $myId,
        ]);

        // 3. Reset attendance status
        if ($att) {
            $pdo->prepare("
                UPDATE attendances SET status = 'incomplete'
                WHERE employee_id = ? AND work_date = ?
            ")->execute([$employeeId, $workDate]);
        }

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        exit();
    }

    // 4. Re-finalize attendance
    try {
        $schedStmt = $pdo->prepare("
            SELECT schedule_date, scheduled_start, scheduled_end
            FROM schedules
            WHERE employee_id = ? AND schedule_date = ?
        ");
        $schedStmt->execute([$employeeId, $workDate]);
        $schedule = $schedStmt->fetch(PDO::FETCH_ASSOC);

        if ($schedule) {
            finalizeEmployeeAttendance($pdo, $employeeId, $schedule, date('Y-m-d H:i:s'));
        }
    } catch (Throwable $e) {}

    echo json_encode(['success' => true, 'message' => 'Log updated successfully.']);
    exit();
}

// ================================================
// EMPLOYEE PATH — editing own log
// ================================================
if (in_array($myRole, ['employee', 'superadmin'])) {

    $employeeId  = $myId;
    $logId       = intval($_POST['log_id']       ?? 0);
    $newDatetime = trim($_POST['new_datetime']   ?? '');
    $reason      = trim($_POST['reason']         ?? '') ?: 'No reason provided';

    if (!$logId || !$newDatetime) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

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

    // ❌ BLOCK IF ALREADY EDITED
    if (hasApprovedLogEdit($pdo, $logId)) {
        echo json_encode([
            'success' => false,
            'message' => 'This log has already been edited.'
        ]);
        exit();
    }

    $newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
    if (!$newDT || $newDT === '1970-01-01 00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
        exit();
    }

    $workDate    = date('Y-m-d', strtotime($log['log_time']));
    $requestType = ($log['log_type'] === 'IN') ? 'time_in' : 'time_out';

    $attStmt = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND work_date = ?");
    $attStmt->execute([$employeeId, $workDate]);
    $att = $attStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // check pending
    $dup = $pdo->prepare("
        SELECT id 
        FROM log_edit_requests 
        WHERE log_id = ? 
          AND status = 'pending'
        LIMIT 1
    ");
    $dup->execute([$logId]);

    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'A pending request for this log entry already exists.']);
        exit();
    }

    try {
        $pdo->prepare("
            INSERT INTO log_edit_requests
                (employee_id, attendance_id, log_id, work_date,
                 request_type, requested_time_in, requested_time_out, reason, status, initiated_by_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ")->execute([
            $employeeId,
            $att['id'] ?? null,
            $logId,
            $workDate,
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

// fallback
echo json_encode(['success' => false, 'message' => 'Unauthorized role.']);