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

    $logStmt = $pdo->prepare("SELECT log_time, log_type, original_log_time FROM logs WHERE id = ? AND employee_id = ?");
    $logStmt->execute([$logId, $employeeId]);
    $log = $logStmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
        exit();
    }

    $reqStmt = $pdo->prepare("SELECT id, status FROM log_edit_requests WHERE log_id = ? ORDER BY id DESC LIMIT 1");
    $reqStmt->execute([$logId]);
    $existingReq = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingReq && $existingReq['status'] === 'approved') {
        echo json_encode(['success' => false, 'message' => 'This log has already been edited.']);
        exit();
    }

    $newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
    if (!$newDT || $newDT === '1970-01-01 00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
        exit();
    }

    $workDate = date('Y-m-d', strtotime($log['log_time']));

    $attStmt = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND work_date = ?");
    $attStmt->execute([$employeeId, $workDate]);
    $att = $attStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    try {
        $pdo->beginTransaction();

        $originalLogTime = $log['original_log_time'] ?? $log['log_time'];

        // Apply new time, preserve original for audit trail
        $pdo->prepare("
            UPDATE logs
            SET original_log_time = COALESCE(original_log_time, log_time),
                log_time           = ?
            WHERE id = ?
        ")->execute([$newDT, $logId]);

        // Record in log_edit_requests as approved
        if ($existingReq) {
            $pdo->prepare("
                UPDATE log_edit_requests
                SET proposed_log_time = ?, reason = ?, requested_by = ?, status = 'approved', approved_by = ?, updated_at = NOW()
                WHERE log_id = ? ORDER BY id DESC LIMIT 1
            ")->execute([$newDT, $reason, $myId, $myId, $logId]);
        } else {
            $pdo->prepare("
                INSERT INTO log_edit_requests
                    (log_id, employee_id, original_log_time, proposed_log_time, reason, requested_by, status)
                VALUES (?, ?, ?, ?, ?, ?, 'approved')
            ")->execute([$logId, $employeeId, $originalLogTime, $newDT, $reason, $myId]);
        }

        // Reset attendance so finalizer can recalculate
        if ($att) {
            $pdo->prepare("UPDATE attendances SET status = 'incomplete' WHERE employee_id = ? AND work_date = ?")
                ->execute([$employeeId, $workDate]);
        }

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        exit();
    }

    // Re-finalize attendance
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
// EMPLOYEE PATH — editing own log (creates pending request)
// ================================================
if ($myRole !== 'superadmin') {

    $employeeId  = $myId;
    $logId       = intval($_POST['log_id']       ?? 0);
    $newDatetime = trim($_POST['new_datetime']   ?? '');
    $reason      = trim($_POST['reason']         ?? '') ?: 'No reason provided';

    if (!$logId || !$newDatetime) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }

    $logStmt = $pdo->prepare("SELECT log_time, log_type, original_log_time FROM logs WHERE id = ? AND employee_id = ?");
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

    $reqStmt = $pdo->prepare("SELECT status FROM log_edit_requests WHERE log_id = ? ORDER BY id DESC LIMIT 1");
    $reqStmt->execute([$logId]);
    $existingReq = $reqStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingReq) {
        if ($existingReq['status'] === 'approved') {
            echo json_encode(['success' => false, 'message' => 'This log has already been edited.']);
            exit();
        }
        if ($existingReq['status'] === 'pending') {
            echo json_encode(['success' => false, 'message' => 'A pending request for this log entry already exists.']);
            exit();
        }
    }

    $newDT = date('Y-m-d H:i:s', strtotime($newDatetime));
    if (!$newDT || $newDT === '1970-01-01 00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
        exit();
    }

    try {
        $originalLogTime = $log['original_log_time'] ?? $log['log_time'];

        $pdo->prepare("
            UPDATE logs
            SET original_log_time = COALESCE(original_log_time, log_time)
            WHERE id = ? AND employee_id = ?
        ")->execute([$logId, $employeeId]);

        $pdo->prepare("
            INSERT INTO log_edit_requests
                (log_id, employee_id, original_log_time, proposed_log_time, reason, requested_by, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ")->execute([$logId, $employeeId, $originalLogTime, $newDT, $reason, $employeeId]);

        echo json_encode(['success' => true, 'message' => 'Log edit request submitted for admin review.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }

    exit();
}

// fallback
echo json_encode(['success' => false, 'message' => 'Unauthorized role.']);
