<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require '../includes/log.php';
require_once(__DIR__ . '/../auth/session_check.php');
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Ensure required data is present
if (!isset($_POST['employee_id'], $_POST['document_type'], $_FILES['document_file'])) {
    die("Missing required data.");
}

$employee_id = intval($_POST['employee_id']);
$safe_document_type = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['document_type']); // Sanitize folder name

$file = $_FILES['document_file'];
$filename = basename($file['name']);

// Validate file upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    die("File upload error: " . $file['error']);
}

// Limit file size (e.g., max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    die("File is too large. Maximum allowed size is 5MB.");
}

// Validate allowed file types
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($file_ext, $allowed_extensions)) {
    die("Invalid file type. Allowed: " . implode(", ", $allowed_extensions));
}

// Generate unique filename to avoid overwrites
$unique_name = uniqid('doc_', true) . '.' . $file_ext;

// Decide subfolder based on document type
$upload_dir = "../uploads/" . $safe_document_type . "/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$target = $upload_dir . $unique_name;
$relative_path = "uploads/" . $safe_document_type . "/" . $unique_name; // For DB storage

if (move_uploaded_file($file['tmp_name'], $target)) {
    $stmt = $conn->prepare("
        INSERT INTO documents (employee_id, document_type, filename, type, file_path)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $employee_id, $safe_document_type, $filename, $safe_document_type, $relative_path);
    $stmt->execute();
    $stmt->close();

    // Log action after successful upload
    logAction($conn, $_SESSION['admin_id'], "Uploaded document '$filename' (stored as '$unique_name') for employee ID: $employee_id");

    header("Location: view_employee.php?id=$employee_id&upload=success");
    exit();
} else {
    die("Failed to upload file. Please check folder permissions.");
}
?>
