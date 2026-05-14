<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once '../includes/log.php';
require_once(__DIR__ . '/../auth/session_check.php');

// Ensure user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Validate inputs
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

if ($doc_id <= 0 || $employee_id <= 0) {
    header("Location: employee.php");
    exit();
}

// Get document info
$stmt = $conn->prepare("SELECT filename, file_path FROM documents WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
$stmt->close();

if ($doc) {
    $file_path = "../" . ltrim($doc['file_path'], "/"); // ensure relative path

    // Only delete if it’s an actual file
    if (is_file($file_path) && file_exists($file_path)) {
        if (!unlink($file_path)) {
            die("Error: Unable to delete file '$file_path'.");
        }
    }

    // Delete DB record
    $del_stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $del_stmt->bind_param("i", $doc_id);
    $del_stmt->execute();
    $del_stmt->close();

    // Log action
    logAction($conn, $_SESSION['admin_id'], "Deleted document '{$doc['filename']}' for Employee ID {$employee_id}");
}

header("Location: view_employee.php?id={$employee_id}&delete=success");
exit();
?>
