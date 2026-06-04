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

$employeeId = $_SESSION['user_id'];
$scheduleId = intval($_POST['schedule_id'] ?? 0);
$newTimeIn  = trim($_POST['new_time_in']  ?? '');
$newTimeOut = trim($_POST['new_time_out'] ?? '');
$reason     = trim($_POST['reason']       ?? '') ?: 'No reason provided';

if (!$scheduleId || !$newTimeIn || !$newTimeOut) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// Validate time format HH:MM
if (!preg_match('/^\d{2}:\d{2}$/', $newTimeIn) || !preg_match('/^\d{2}:\d{2}$/', $newTimeOut)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format.']);
    exit();
}

// Fetch and verify schedule belongs to this employee
$schedStmt = $pdo->prepare("
    SELECT id, schedule_date, scheduled_start, scheduled_end, batch_id, orig_scheduled_start
    FROM schedules
    WHERE id = ? AND employee_id = ? AND is_rest_day = 0 AND COALESCE(is_archived, 0) = 0
");
$schedStmt->execute([$scheduleId, $employeeId]);
$sched = $schedStmt->fetch(PDO::FETCH_ASSOC);

if (!$sched) {
    echo json_encode(['success' => false, 'message' => 'Schedule not found.']);
    exit();
}

// Block if there is already a pending edit request for this schedule
if ($sched['batch_id']) {
    $pendingCheck = $pdo->prepare("SELECT id FROM schedule_edit_requests WHERE batch_id = ? AND status = 'pending'");
    $pendingCheck->execute([$sched['batch_id']]);
    if ($pendingCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already have a pending edit request for this schedule.']);
        exit();
    }
}

$schedDate   = $sched['schedule_date'];
$isOvernight = $newTimeOut < $newTimeIn;
$newStart    = $schedDate . ' ' . $newTimeIn . ':00';
$newEnd      = ($isOvernight ? date('Y-m-d', strtotime($schedDate . ' +1 day')) : $schedDate) . ' ' . $newTimeOut . ':00';

$batchId = bin2hex(random_bytes(8));

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE schedules SET
            orig_scheduled_start = COALESCE(orig_scheduled_start, scheduled_start),
            orig_scheduled_end   = COALESCE(orig_scheduled_end, scheduled_end),
            orig_is_rest_day     = COALESCE(orig_is_rest_day, is_rest_day),
            scheduled_start      = ?,
            scheduled_end        = ?,
            request_type         = 'edit',
            batch_id             = ?,
            updated_at           = NOW()
        WHERE id = ?
    ")->execute([$newStart, $newEnd, $batchId, $scheduleId]);

    $serStmt = $pdo->prepare("
        INSERT INTO schedule_edit_requests (batch_id, employee_id, reason, requested_by, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $serStmt->execute([$batchId, $employeeId, $reason, $employeeId]);
    $serId = (int)$pdo->lastInsertId();

    // Activity-log entry so the request shows up in the logs as its own type.
    // Status is read from schedule_edit_requests via schedule_request_id; before/after
    // times come from the schedule's orig_*/scheduled_* columns set above.
    $pdo->prepare("
        INSERT INTO logs (employee_id, log_type, log_time, longitude, latitude, is_within_office, schedule_request_id, edit_requested_by, edit_reason)
        VALUES (?, 'REQUEST_CHANGE_SCHEDULE', NOW(), 0, 0, 0, ?, ?, ?)
    ")->execute([$employeeId, $serId, $employeeId, $batchId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Schedule edit request submitted for admin approval.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
