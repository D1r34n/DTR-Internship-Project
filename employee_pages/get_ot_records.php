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
        date,
        scheduled_time_in,
        scheduled_time_out,
        actual_time_in,
        actual_time_out,
        overtime_minutes,
        late_minutes,
        status
    FROM attendance
    WHERE employee_id = ?
    AND overtime_minutes > 0
    AND actual_time_in IS NOT NULL
    AND (overtime_status = 'none' OR overtime_status = '' OR overtime_status IS NULL)
    ORDER BY date DESC
");

$stmt->execute([$employeeId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($records);