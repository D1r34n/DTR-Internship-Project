<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// ✅ Ensure employee is logged in
if (!isset($_SESSION['employee_code'])) {
    header("Location: ../auth/login.php");
    exit();
}

// ✅ Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

$employee_code = $_SESSION['employee_code'];
$type = $_POST['type'] ?? null;
$reason = trim($_POST['reason'] ?? '');

// ✅ Validate type
$allowedTypes = ['COE', 'COE_BASIC'];
if (!in_array($type, $allowedTypes)) {
    $_SESSION['flash_message'] = "❌ Invalid request type.";
    header("Location: profile.php");
    exit();
}

// ✅ Validate reason
if (empty($reason)) {
    $_SESSION['flash_message'] = "⚠️ Please select a reason for your request.";
    header("Location: profile.php");
    exit();
}

// ✅ Check if there is already a pending request of this type
$check = $conn->prepare("
    SELECT id 
    FROM employee_requests 
    WHERE employee_code = ? 
      AND request_type = ? 
      AND status = 'pending'
");
$check->bind_param("ss", $employee_code, $type);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $_SESSION['flash_message'] = "⚠️ You already have a pending request for this type.";
    $check->close();
    $conn->close();
    header("Location: profile.php");
    exit();
}
$check->close();

// ✅ Insert new request (with reason)
$stmt = $conn->prepare("
    INSERT INTO employee_requests (employee_code, request_type, reason, status, date_requested) 
    VALUES (?, ?, ?, 'pending', NOW())
");
$stmt->bind_param("sss", $employee_code, $type, $reason);

if ($stmt->execute()) {
    $_SESSION['flash_message'] = "✅ Your COE request has been submitted!";
} else {
    error_log("DB Error on COE request: " . $stmt->error);
    $_SESSION['flash_message'] = "❌ Something went wrong. Please try again.";
}

$stmt->close();
$conn->close();

// ✅ Redirect back to profile
header("Location: profile.php");
exit();
