<?php
session_start();
require_once 'db.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    exit();
}

$employeeId = $_SESSION['user_id'];

// Check current status
$stmt = $pdo->prepare("
    SELECT log_type 
    FROM logs 
    WHERE employee_id = ?
    ORDER BY log_time DESC 
    LIMIT 1
");
$stmt->execute([$employeeId]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

$isTimedIn = ($lastLog && $lastLog['log_type'] === 'login');

// Toggle if time in or time out
if (!$isTimedIn) {
    $stmt = $pdo->prepare("INSERT INTO logs (employee_id, log_type) VALUES (?, 'login')");
    $stmt->execute([$employeeId]);
    $status = "timed_in";
} else {
    $stmt = $pdo->prepare("INSERT INTO logs (employee_id, log_type) VALUES (?, 'logout')");
    $stmt->execute([$employeeId]);
    $status = "timed_out";
}

// Return status of employee
header('Content-Type: application/json');

echo json_encode([
    "status" => $status
]);