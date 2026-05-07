<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$logId      = intval($_POST['log_id']      ?? 0);
$employeeId = intval($_POST['employee_id'] ?? 0);
$logType    = trim($_POST['log_type']      ?? '');
$newDate    = trim($_POST['new_date']      ?? '');
$newTime    = trim($_POST['new_time']      ?? '');

if (!$logId || !$employeeId || !$logType || !$newDate || !$newTime) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

if (!in_array($logType, ['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid log type.']);
    exit();
}

$newDT = date('Y-m-d H:i:s', strtotime("$newDate $newTime"));
if (!$newDT || $newDT === '1970-01-01 00:00:00') {
    echo json_encode(['success' => false, 'message' => 'Invalid date/time.']);
    exit();
}

// Verify the log exists and belongs to this employee
$logStmt = $pdo->prepare("SELECT log_time, log_type FROM logs WHERE id = ? AND employee_id = ?");
$logStmt->execute([$logId, $employeeId]);
$log = $logStmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
    exit();
}

$oldWorkDate = date('Y-m-d', strtotime($log['log_time']));
$newWorkDate = date('Y-m-d', strtotime($newDT));

try {
    $pdo->beginTransaction();

    // Update the log record directly
    $pdo->prepare("UPDATE logs SET log_type = ?, log_time = ? WHERE id = ? AND employee_id = ?")
        ->execute([$logType, $newDT, $logId, $employeeId]);

    // Sync attendance for IN / OUT
    if (in_array($logType, ['IN', 'OUT'])) {
        $attStmt = $pdo->prepare("SELECT * FROM attendances WHERE employee_id = ? AND work_date = ?");
        $attStmt->execute([$employeeId, $newWorkDate]);
        $att = $attStmt->fetch(PDO::FETCH_ASSOC);

        if ($att) {
            if ($logType === 'IN') {
                $pdo->prepare("
                    UPDATE attendances SET
                        actual_time_in     = ?,
                        late_minutes       = GREATEST(0, TIMESTAMPDIFF(MINUTE, scheduled_start, ?)),
                        total_work_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, ?, COALESCE(actual_time_out, scheduled_end)) - COALESCE(break_minutes, 0)),
                        status             = 'present'
                    WHERE id = ?
                ")->execute([$newDT, $newDT, $newDT, $att['id']]);
            } elseif ($logType === 'OUT') {
                $pdo->prepare("
                    UPDATE attendances SET
                        actual_time_out    = ?,
                        missed_time_out    = 0,
                        status             = IF(actual_time_in IS NOT NULL, 'present', status),
                        total_work_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, COALESCE(actual_time_in, scheduled_start), ?) - COALESCE(break_minutes, 0)),
                        undertime_minutes  = GREATEST(0, TIMESTAMPDIFF(MINUTE, ?, scheduled_end)),
                        overtime_minutes   = GREATEST(0, TIMESTAMPDIFF(MINUTE, scheduled_end, ?))
                    WHERE id = ?
                ")->execute([$newDT, $newDT, $newDT, $newDT, $att['id']]);
            }
        }
    }

    // If the work date changed and the old type was IN/OUT, clear the old attendance field
    if ($oldWorkDate !== $newWorkDate && in_array($log['log_type'], ['IN', 'OUT'])) {
        $oldAttStmt = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND work_date = ?");
        $oldAttStmt->execute([$employeeId, $oldWorkDate]);
        $oldAtt = $oldAttStmt->fetch(PDO::FETCH_ASSOC);
        if ($oldAtt) {
            if ($log['log_type'] === 'IN') {
                $pdo->prepare("UPDATE attendances SET actual_time_in = NULL, late_minutes = 0, total_work_minutes = 0 WHERE id = ?")->execute([$oldAtt['id']]);
            } elseif ($log['log_type'] === 'OUT') {
                $pdo->prepare("UPDATE attendances SET actual_time_out = NULL, missed_time_out = 1, undertime_minutes = 0, overtime_minutes = 0 WHERE id = ?")->execute([$oldAtt['id']]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Log updated successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
