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
$obDate     = trim($_POST['ob_date']     ?? '');
$clientName = trim($_POST['client_name'] ?? '');
$reason     = trim($_POST['reason']      ?? '');

// ---- BASIC VALIDATION ----
if (!$obDate || !$clientName || !$reason) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $obDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit();
}

// ---- MUST BE A SCHEDULED WORK DAY ----
$schedCheck = $pdo->prepare("
    SELECT COUNT(*) FROM schedules
    WHERE employee_id = ? AND schedule_date = ? AND is_rest_day = 0
");
$schedCheck->execute([$employeeId, $obDate]);
if ($schedCheck->fetchColumn() == 0) {
    echo json_encode(['success' => false, 'message' => 'No work schedule found for the selected date.']);
    exit();
}

// ---- CHECK FOR DUPLICATE ----
$dupCheck = $pdo->prepare("
    SELECT COUNT(*) FROM ob_requests
    WHERE employee_id = ? AND ob_date = ? AND status IN ('pending', 'approved')
");
$dupCheck->execute([$employeeId, $obDate]);
if ($dupCheck->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have an OB request for this date.']);
    exit();
}

// ---- INSERT ----
try {
    $pdo->prepare("
        INSERT INTO ob_requests (employee_id, ob_date, client_name, reason, status)
        VALUES (?, ?, ?, ?, 'pending')
    ")->execute([$employeeId, $obDate, $clientName, $reason]);

    echo json_encode(['success' => true, 'message' => 'OB request submitted successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
