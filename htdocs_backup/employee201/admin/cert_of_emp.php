<?php
session_start();
require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require '../vendor/autoload.php';

// Block staff from accessing this page
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'staff') {
    die("Access denied: You do not have permission to generate certificates.");
}

use Mpdf\Mpdf;

// Get employee ID (works with POST or GET)
$employee_id = $_POST['id'] ?? ($_GET['id'] ?? null);
if (!$employee_id) {
    die("Invalid employee ID");
}

// Explicit default to COE
$type = $_POST['request_type'] ?? ($_GET['type'] ?? 'COE');

// Restrict salary certificate to superadmin only
if ($type === 'COE_BASIC' && (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin')) {
    die("⛔ Access denied. Only Superadmin can generate COE with Basic Salary.");
}

// Fetch employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found");
}

// --- Update request status if request_id is provided ---
if (isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];

    $update = $conn->prepare("UPDATE employee_requests SET status = 'approved' WHERE id = ?");
    $update->bind_param("i", $request_id);
    $update->execute();
    $update->close();

    $admin_id = $_SESSION['admin_id'];
    $desc = "Approved {$type} request and generated {$type} for employee {$employee['first_name']} {$employee['last_name']} (Employee Code: {$employee['employee_code']})";

    $log = $conn->prepare("INSERT INTO logs (admin_id, action, log_time, visible_to) VALUES (?, ?, NOW(), 'all')");
    $log->bind_param("is", $admin_id, $desc);
    $log->execute();
    $log->close();
} else {
    $admin_id = $_SESSION['admin_id'];
    $desc = "Generated {$type} for employee {$employee['first_name']} {$employee['last_name']} (Employee Code: {$employee['employee_code']})";

    $log = $conn->prepare("INSERT INTO logs (admin_id, action, log_time, visible_to) VALUES (?, ?, NOW(), 'all')");
    $log->bind_param("is", $admin_id, $desc);
    $log->execute();
    $log->close();
}

// Generate Incremental Certificate Number
$last_stmt = $conn->query("SELECT MAX(certificate_no) AS last_no FROM certificate_numbers");
$row = $last_stmt->fetch_assoc();
$last_no = $row['last_no'] ?? 0;
$new_no = $last_no + 1;

$insert_stmt = $conn->prepare("INSERT INTO certificate_numbers (employee_code, certificate_no) VALUES (?, ?)");
$insert_stmt->bind_param("ii", $employee['employee_code'], $new_no);
$insert_stmt->execute();
$insert_stmt->close();

$formatted_cert_no = str_pad($new_no, 10, "0", STR_PAD_LEFT);

// Company fixed values
$company_name           = "HSN Philippines";
$company_address        = "1172-1180 President Quirino Avenue Extension Paco, Manila, Philippines";
$company_contact        = "282484688";
$company_representative = "Mary Grace Honrade";

// ✅ Base64 encode the logo — GD confirmed enabled, this is the most reliable method
$logo_file   = __DIR__ . "/../assets/img/logo2.png";
$logo_base64 = base64_encode(file_get_contents($logo_file));
$logo_src    = "data:image/png;base64," . $logo_base64;

// Format dates
$date_start   = date("F d, Y", strtotime($employee['date_hired']));
$date_end     = $employee['date_of_separation'] ? date("F d, Y", strtotime($employee['date_of_separation'])) : "Present";
$current_date = date("F d, Y");

$html = "
<table width='100%' style='margin-bottom: 0; border-collapse: collapse;'>
    <tr>
        <td style='width: 120px; vertical-align: top;'>
        </td>
        <td style='text-align: center; vertical-align: middle;'>
            <h2 style='margin: 0 0 4px 0;'>$company_name</h2>
            <p style='margin: 2px 0;'>$company_address</p>
            <p style='margin: 2px 0;'>Contact: $company_contact</p>
        </td>
        <td style='width: 160px; vertical-align: top; text-align: right;'>
            <img src='$logo_src' style='width:100px; height:auto;'>
        </td>
    </tr>
</table>

<p style='margin: 10px 0 0 0; font-size:14px; text-align:right;'><strong>Certificate No: $formatted_cert_no</strong></p>
<hr style='margin: 45px 0;'>

<h3 style='text-align: center;'>CERTIFICATE OF EMPLOYMENT</h3>

<p style='text-align: justify; font-size: 14px; line-height: 1.5;'>
    This is to certify that <strong>{$employee['first_name']} {$employee['middle_name']} {$employee['last_name']}</strong> 
    has been employed with <strong>$company_name</strong> from <strong>$date_start</strong> to <strong>$date_end</strong> 
    as <strong>{$employee['position']}</strong>.
</p>
";

if ($type === 'COE_BASIC' && $_SESSION['admin_role'] === 'superadmin') {
    $html .= "
    <p style='text-align: justify; font-size: 14px; line-height: 1.5;'>
        As of this date, his/her monthly basic salary is 
        <strong>&#8369;" . number_format($employee['basic_salary'], 2) . "</strong>.
    </p>
    ";
}

$html .= "
<p style='text-align: justify; font-size: 14px; line-height: 1.5;'>
    This certification is issued upon the request of the above-mentioned name for whatever legal purpose it may serve.
</p>

<p style='margin-top: 40px; font-size: 14px;'>Issued this <strong>$current_date</strong> in <strong>$company_address</strong>.</p>

<div style='margin-top: 200px; text-align: left;'>
    <p style='font-size: 14px; margin-bottom: 0;'><strong>$company_representative</strong></p>
    <p style='font-size: 14px; margin-top: 2px;'>Authorized Company Representative</p>
</div>
";

// ✅ Use sys_get_temp_dir() to avoid Windows temp folder permission issues
$mpdf = new Mpdf([
    'mode'    => 'utf-8',
    'format'  => 'A4',
    'tempDir' => sys_get_temp_dir(),
]);

$mpdf->WriteHTML($html);
$mpdf->Output("Certificate_of_Employment.pdf", "I");

$conn->close();
?>