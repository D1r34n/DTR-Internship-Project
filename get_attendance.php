<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$employeeId = $_SESSION['user_id'];

// Get all attendance records for this user
$stmt = $pdo->prepare("
    SELECT 
        date,
        time_in,
        time_out,
        total_work_hours,
        status
    FROM attendance
    WHERE employee_id = ?
    ORDER BY date DESC
");
$stmt->execute([$employeeId]);

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON
header('Content-Type: application/json');

echo json_encode($records);