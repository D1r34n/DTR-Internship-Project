<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$request_id = $_POST['request_id'] ?? 0;
$action = $_POST['action'] ?? '';

if ($request_id) {
    // Fetch request details (for logging)
    $stmt = $conn->prepare("
        SELECT r.id, r.request_type, r.employee_code, e.first_name, e.last_name
        FROM employee_requests r
        JOIN employees e ON r.employee_code = e.employee_code
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $stmt->close();

    if ($request) {
        $admin_id = $_SESSION['admin_id'];
        $admin_username = $_SESSION['admin_username'] ?? 'Unknown';

        if ($action === 'decline') {
            // Mark as declined
            $stmt = $conn->prepare("UPDATE employee_requests SET status = 'declined' WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            // Log action
            $desc = "Declined {$request['request_type']} request for {$request['first_name']} {$request['last_name']} (Employee Code: {$request['employee_code']})";
            $log = $conn->prepare("INSERT INTO logs (admin_id, action, log_time, visible_to) VALUES (?, ?, NOW(), 'all')");
            $log->bind_param("is", $admin_id, $desc);
            $log->execute();
            $log->close();

        } elseif ($action === 'delete') {
            // Permanently delete
            $stmt = $conn->prepare("DELETE FROM employee_requests WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $stmt->close();

            // Log action
            $desc = "Deleted {$request['request_type']} request for {$request['first_name']} {$request['last_name']} (Employee Code: {$request['employee_code']})";
            $log = $conn->prepare("INSERT INTO logs (admin_id, action, log_time, visible_to) VALUES (?, ?, NOW(), 'all')");
            $log->bind_param("is", $admin_id, $desc);
            $log->execute();
            $log->close();
        }
    }
}

$conn->close();
header("Location: employee_requests.php");
exit;
