<?php
session_start();
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';

// Get parameters
$report_id = (int)($_GET['id'] ?? 0);
$employee_id = (int)($_GET['employee_id'] ?? 0);

if (!$report_id || !$employee_id) {
    header("Location: view_employee.php?id=$employee_id");
    exit;
}

// Verify the report exists and belongs to this employee
$verify_stmt = $conn->prepare("SELECT id, evidence_file FROM incident_reports WHERE id = ? AND employee_id = ?");
$verify_stmt->bind_param("ii", $report_id, $employee_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
$report = $verify_result->fetch_assoc();
$verify_stmt->close();

if (!$report) {
    header("Location: view_employee.php?id=$employee_id");
    exit;
}

// Delete evidence file if it exists
if (!empty($report['evidence_file'])) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/tres marias/employee201/' . $report['evidence_file'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Delete tardiness records associated with this IR
$tardiness_delete = $conn->prepare("DELETE FROM incident_report_tardiness WHERE incident_report_id = ?");
$tardiness_delete->bind_param("i", $report_id);
$tardiness_delete->execute();
$tardiness_delete->close();

// Delete the incident report
$delete_stmt = $conn->prepare("DELETE FROM incident_reports WHERE id = ?");
$delete_stmt->bind_param("i", $report_id);

if ($delete_stmt->execute()) {
    $delete_stmt->close();
    $conn->close();
    header("Location: view_employee.php?id=$employee_id&deleted=1");
    exit;
} else {
    $delete_stmt->close();
    $conn->close();
    header("Location: view_employee.php?id=$employee_id&error=1");
    exit;
}
?>