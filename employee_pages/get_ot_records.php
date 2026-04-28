<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        work_date       AS date,
        scheduled_start AS scheduled_time_in,
        scheduled_end   AS scheduled_time_out,
        actual_time_in,
        actual_time_out,
        overtime_minutes,
        late_minutes,
        overtime_status,
        status
    FROM attendances
    WHERE employee_id = ?
    AND overtime_minutes > 0
    AND actual_time_in IS NOT NULL
    AND overtime_status IN ('none', 'pending')
    ORDER BY work_date DESC
");

$stmt->execute([$employeeId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($records);