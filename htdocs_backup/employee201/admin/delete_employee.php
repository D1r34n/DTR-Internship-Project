<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once(__DIR__ . '/../auth/session_check.php');

require_once __DIR__ . '/../includes/config.php';
require '../includes/log.php';

// Validate employee ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: manage_employee.php?error=invalid_id");
    exit;
}

// =============================
// 1. Fetch the employee_code first
// =============================
$emp_stmt = $conn->prepare("SELECT employee_code FROM employees WHERE id = ?");
$emp_stmt->bind_param("i", $id);
$emp_stmt->execute();
$emp_stmt->bind_result($emp_code);
$emp_stmt->fetch();
$emp_stmt->close();

// If no employee found, stop here
if (empty($emp_code)) {
    header("Location: manage_employee.php?error=not_found");
    exit;
}

// =============================
// 2. Delete associated documents (files + DB)
// =============================
$doc_stmt = $conn->prepare("SELECT filename, file_path FROM documents WHERE employee_id = ?");
$doc_stmt->bind_param("i", $id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();

while ($row = $doc_result->fetch_assoc()) {
    if (!empty($row['file_path'])) {
        $filePath = "../" . ltrim($row['file_path'], "/");
        if (is_file($filePath) && file_exists($filePath)) {
            @unlink($filePath); // delete the file if it exists
        }
    }
}
$doc_stmt->close();

$del_docs = $conn->prepare("DELETE FROM documents WHERE employee_id = ?");
$del_docs->bind_param("i", $id);
$del_docs->execute();
$del_docs->close();

// =============================
// 3. Delete employee record
// =============================
$stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// =============================
// 4. Delete employee login credentials using employee_code
// =============================
$del_user = $conn->prepare("DELETE FROM employee_users WHERE employee_code = ?");
$del_user->bind_param("s", $emp_code); // employee_code is usually VARCHAR
$del_user->execute();
$del_user->close();

// =============================
// 5. Log the action
// =============================
logAction($conn, $_SESSION['admin_id'], "Deleted employee ID: {$id}, Code: {$emp_code}, and all associated documents");

// =============================
// 6. Redirect after deletion
// =============================
header("Location: manage_employee.php?deleted=1");
exit;
?>
