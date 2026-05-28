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

$employeeId    = $_SESSION['user_id'];
$autoApprove   = in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager']);
$initialStatus = $autoApprove ? 'approved' : 'pending';
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

// ---- CHECK FOR CONFLICT WITH ANY EXISTING LEAVE OR OB REQUEST ----
$dupCheck = $pdo->prepare("
    SELECT COUNT(*) FROM leave_requests
    WHERE employee_id = ? AND JSON_CONTAINS(selected_dates, JSON_QUOTE(?)) AND status IN ('pending', 'approved')
");
$dupCheck->execute([$employeeId, $obDate]);
if ($dupCheck->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a leave or OB request for this date.']);
    exit();
}

// ---- INSERT INTO leave_requests ----
try {
    $obLeaveTypeId = (int)$pdo->query("SELECT id FROM leave_types WHERE name = 'ob leave'")->fetchColumn();
    $pdo->prepare("
        INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, selected_dates, reason, client_name, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$employeeId, $obLeaveTypeId, $obDate, $obDate, json_encode([$obDate]), $reason, $clientName, $initialStatus]);

    $msg = $autoApprove ? 'OB request approved.' : 'OB request submitted successfully!';
    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
