<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        a.id               AS attendance_id,
        a.work_date,
        a.scheduled_start,
        a.scheduled_end,
        a.actual_time_in,
        a.actual_time_out,
        a.undertime_minutes,
        a.late_minutes
    FROM attendances a
    WHERE a.employee_id = ?
    AND a.actual_time_in IS NOT NULL
    AND (
        (a.actual_time_out IS NULL AND a.scheduled_end < NOW())
        OR (a.actual_time_out IS NOT NULL AND a.actual_time_out < a.scheduled_end)
        OR (a.actual_time_in > a.scheduled_start)
    )
    AND NOT EXISTS (
        SELECT 1 FROM log_edit_requests le
        WHERE le.attendance_id = a.id
        AND le.status IN ('pending', 'approved')
    )
    ORDER BY a.work_date DESC
    LIMIT 30
");
$stmt->execute([$employeeId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $types = [];
    if (is_null($row['actual_time_out']) && strtotime($row['scheduled_end']) < time()) {
        $types[] = 'no_timeout';
    } elseif (!is_null($row['actual_time_out']) && $row['actual_time_out'] < $row['scheduled_end']) {
        $types[] = 'undertime';
    }
    if ($row['actual_time_in'] > $row['scheduled_start']) {
        $types[] = 'late';
    }
    $row['applicable_types'] = $types;
    $row['record_type']      = $types[0] ?? 'late';
}

echo json_encode($rows);
