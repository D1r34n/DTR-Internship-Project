<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];

$date     = $_POST['date']   ?? null;
$time_in  = $_POST['time_in']  ?? null;
$time_out = $_POST['time_out'] ?? null;
$reason   = trim($_POST['reason'] ?? '');

// Basic validation
if (!$date || !$time_in || !$time_out || !$reason) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

try {
    // 1. Insert into overtime_requests
    $stmt = $pdo->prepare("
        INSERT INTO overtime_requests (employee_id, date, time_in, time_out, reason, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$employeeId, $date, $time_in, $time_out, $reason]);

    // 2. Update attendances overtime_status to 'pending'
    $stmt2 = $pdo->prepare("
        UPDATE attendances 
        SET overtime_status = 'pending'
        WHERE employee_id = ? AND date = ?
    ");
    $stmt2->execute([$employeeId, $date]);

    echo json_encode(['success' => true, 'message' => 'OT request submitted successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}