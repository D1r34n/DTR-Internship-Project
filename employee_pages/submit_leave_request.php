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
$leaveType  = trim($_POST['leave_type']  ?? '');
$startDate  = trim($_POST['start_date']  ?? '');
$endDate    = trim($_POST['end_date']    ?? '');
$reason     = trim($_POST['reason']      ?? '');

// ---- VALIDATION ----
if (!$leaveType || !$startDate || !$endDate || !$reason) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

$validTypes = ['sick leave', 'vacation leave', 'birthday leave', 'solo parent leave'];
if (!in_array(strtolower($leaveType), $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave type.']);
    exit();
}

$today = date('Y-m-d');
if ($startDate <= $today || $endDate <= $today) {
    echo json_encode(['success' => false, 'message' => 'Only future dates are allowed.']);
    exit();
}

if ($startDate > $endDate) {
    echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date.']);
    exit();
}

// ---- CHECK FOR EXISTING PENDING REQUEST ----
$check = $pdo->prepare("
    SELECT COUNT(*) FROM leave_requests
    WHERE employee_id = ?
    AND status = 'pending'
    AND (
        start_date BETWEEN ? AND ?
        OR end_date BETWEEN ? AND ?
    )
");
$check->execute([$employeeId, $startDate, $endDate, $startDate, $endDate]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending leave request for these dates.']);
    exit();
}

// ---- INSERT ----
try {
    $pdo->prepare("
        INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ")->execute([$employeeId, $leaveType, $startDate, $endDate, $reason]);

    echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}