<?php
session_start();
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require '../vendor/autoload.php'; // Make sure mpdf is installed

use Mpdf\Mpdf;

// Get employee ID
$employee_id = $_GET['id'] ?? null;
if (!$employee_id) {
    die("Invalid employee ID");
}

// Fetch employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("Employee not found");
}

// Company fixed values
$company_name = "HSN Philippines";
$company_address = "1172-1180 President Quirino Avenue Extension Paco, Manila, Philippines";
$company_contact = "282484688";
$company_logo_path = "../uploads/logo/logo.png"; // Fixed logo path
$company_representative = "Federico Acierto III"; // Fixed representative name

// Format dates
$date_start = date("F d, Y", strtotime($employee['date_hired']));
$date_end = $employee['date_end'] ? date("F d, Y", strtotime($employee['date_end'])) : "Present";
$current_date = date("F d, Y");

// Prepare HTML for PDF
$html = "
<div style='text-align: center;'>
    <img src='$company_logo_path' style='width:100px;'><br>
    <h2 style='margin: 5px 0;'>$company_name</h2>
    <p style='margin: 2px 0;'>$company_address</p>
    <p style='margin: 2px 0;'>Contact: $company_contact</p>
    <hr style='margin: 20px 0;'>
</div>

<h3 style='text-align: center;'>CERTIFICATE OF EMPLOYMENT</h3>

<p style='text-align: justify; font-size: 14px; line-height: 1.5;'>
    This is to certify that <strong>{$employee['first_name']} {$employee['last_name']}</strong> 
    has been employed with <strong>$company_name</strong> from <strong>$date_start</strong> to <strong>$date_end</strong> 
    as <strong>{$employee['position']}</strong>.
</p>

<p style='text-align: justify; font-size: 14px; line-height: 1.5;'>
    This certification is issued upon the request of the above-mentioned name for whatever legal purpose it may serve.
</p>

<p style='margin-top: 40px; font-size: 14px;'>Issued this <strong>$current_date</strong> in <strong>$company_address</strong>.</p>

<div style='margin-top: 60px; text-align: center;'>
    <p><strong>$company_representative</strong></p>
    <p>Authorized Company Representative</p>
</div>
";

// Generate PDF
$mpdf = new Mpdf();
$mpdf->WriteHTML($html);
$mpdf->Output("Certificate_of_Employment.pdf", "I");
