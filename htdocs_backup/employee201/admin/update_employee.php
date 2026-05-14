<?php
session_start();
require_once(__DIR__ . '/../auth/session_check.php');
// Redirect if not authenticated
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';

// Validate and sanitize POST data
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    error_log("Invalid employee ID received.");
    die("Invalid employee ID.");
}


// Capture employee fields
$employee_code    = trim(filter_input(INPUT_POST, 'employee_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$first_name       = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$middle_name      = trim(filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$last_name        = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$birthdate        = trim($_POST['birthdate'] ?? '');
$contact_no       = trim(filter_input(INPUT_POST, 'contact_no', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$present_address  = trim(filter_input(INPUT_POST, 'present_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$permanent_address= trim(filter_input(INPUT_POST, 'permanent_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$emergency_name   = trim(filter_input(INPUT_POST, 'emergency_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$emergency_no     = trim(filter_input(INPUT_POST, 'emergency_no', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$position         = trim(filter_input(INPUT_POST, 'position', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$department       = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$employment_status= trim(filter_input(INPUT_POST, 'employment_status', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$gender           = trim(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$ss_number        = trim(filter_input(INPUT_POST, 'ss_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$tin_number       = trim(filter_input(INPUT_POST, 'tin_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$philhealth_number= trim(filter_input(INPUT_POST, 'philhealth_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$hmdf_number      = trim(filter_input(INPUT_POST, 'hmdf_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));


// Validate required fields
$required_fields = ['employee_code', 'first_name', 'last_name', 'position', 'department', 'employment_status', 'gender'];
foreach ($required_fields as $field) {
    if (empty($$field)) {
        error_log("Missing required field: $field");
        die("All required fields must be filled.");
    }
}

// Validate birthdate format (YYYY-MM-DD)
if ($birthdate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
    error_log("Invalid birthdate format: $birthdate");
    die("Invalid birthdate format.");
}

// Handle basic salary (superadmin only)
$basic_salary = null;
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') {
    if (array_key_exists('basic_salary', $_POST)) {
        $basic_salary_raw = str_replace(',', '', trim($_POST['basic_salary']));
        if ($basic_salary_raw !== '' && is_numeric($basic_salary_raw)) {
            $basic_salary = (float)$basic_salary_raw;
        } else {
            error_log("Invalid basic_salary value: " . $_POST['basic_salary']);
        }
    }
}

// Dates from form
$date_resigned = !empty($_POST['date_resigned']) ? $_POST['date_resigned'] : null;
$date_retired  = !empty($_POST['date_retired'])  ? $_POST['date_retired']  : null;
$date_fired    = !empty($_POST['date_fired'])    ? $_POST['date_fired']    : null;

// Handle separation-related dates
$date_resigned = !empty($_POST['date_resigned']) ? $_POST['date_resigned'] : null;
$date_retired  = !empty($_POST['date_retired'])  ? $_POST['date_retired']  : null;
$date_fired    = !empty($_POST['date_fired'])    ? $_POST['date_fired']    : null;

// Always just use one separation date field
$date_of_separation = !empty($_POST['date_of_separation']) ? $_POST['date_of_separation'] : null;


// Fetch old values for comparison
$oldData = [];
$stmtOld = $conn->prepare("SELECT * FROM employees WHERE id=?");
$stmtOld->bind_param("i", $id);
$stmtOld->execute();
$resultOld = $stmtOld->get_result();
if ($resultOld && $resultOld->num_rows > 0) {
    $oldData = $resultOld->fetch_assoc();
}
$stmtOld->close();

$conn->begin_transaction();

try {
    // Update employee info
    if ($basic_salary !== null) {
        $stmt = $conn->prepare("
            UPDATE employees 
            SET employee_code=?, first_name=?, middle_name=?, last_name=?, gender=?, birthdate=?, contact_no=?, present_address=?, permanent_address=?, 
                emergency_name=?, emergency_no=?, position=?, department=?, employment_status=?, 
                date_resigned=?, date_fired=?, date_retired=?, date_of_separation=?, 
                ss_number=?, tin_number=?, philhealth_number=?, hmdf_number=?, basic_salary=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssssssssssssssssssssssdi",
            $employee_code, $first_name, $middle_name, $last_name, $gender, $birthdate, $contact_no, $present_address, $permanent_address, 
            $emergency_name, $emergency_no, $position, $department, $employment_status,
            $date_resigned, $date_fired, $date_retired, $date_of_separation,
            $ss_number, $tin_number, $philhealth_number, $hmdf_number,
            $basic_salary, $id
        );
    } else {
        $stmt = $conn->prepare("
            UPDATE employees 
            SET employee_code=?, first_name=?, middle_name=?, last_name=?, gender=?, birthdate=?, contact_no=?, present_address=?, permanent_address=?, 
                emergency_name=?, emergency_no=?, position=?, department=?, employment_status=?, 
                date_resigned=?, date_fired=?, date_retired=?, date_of_separation=?, 
                ss_number=?, tin_number=?, philhealth_number=?, hmdf_number=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssssssssssssssssssssssi",
            $employee_code, $first_name, $middle_name, $last_name, $gender, $birthdate, $contact_no, $present_address, $permanent_address,
            $emergency_name, $emergency_no, $position, $department, $employment_status,
            $date_resigned, $date_fired, $date_retired, $date_of_separation,
            $ss_number, $tin_number, $philhealth_number, $hmdf_number, $id
        );
    }
    if (!$stmt->execute()) {
        throw new Exception("Failed to update employee: " . $stmt->error);
    }
    $stmt->close();

    // ================================
    // Handle document uploads (NO "type" column)
    // ================================
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $document_types = $_POST['document_type'] ?? [];
    $document_files = $_FILES['document_file'] ?? [];

    if (!empty($document_types) && !empty($document_files['name'])) {
        for ($i = 0; $i < count($document_types); $i++) {
            $doc_type = trim($document_types[$i]);
            if (empty($doc_type) || empty($document_files['name'][$i])) continue;

            $tmp_name = $document_files['tmp_name'][$i];
            $ext = strtolower(pathinfo($document_files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) continue;
            $filesize = $document_files['size'][$i];
            if ($filesize > $max_size) continue;

            $target_dir = __DIR__ . '/../Uploads/' . $doc_type . '/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

            $new_filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $server_path = $target_dir . $new_filename;
            $db_path = 'Uploads/' . $doc_type . '/' . $new_filename;

            // Check if document already exists for this type
            $check = $conn->prepare("SELECT id, file_path FROM documents WHERE employee_id=? AND document_type=?");
            $check->bind_param("is", $id, $doc_type);
            $check->execute();
            $result = $check->get_result();

            if ($row = $result->fetch_assoc()) {
                // Replace old file
                $old_file = __DIR__ . '/../' . $row['file_path'];
                if (is_file($old_file)) unlink($old_file);

                $stmt = $conn->prepare("UPDATE documents SET file_path=?, uploaded_at=NOW() WHERE id=?");
                $stmt->bind_param("si", $db_path, $row['id']);
                $logDocAction = "Updated document [$doc_type], new file: $db_path";
            } else {
                $stmt = $conn->prepare("INSERT INTO documents (employee_id, document_type, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $id, $doc_type, $db_path);
                $logDocAction = "Uploaded new document [$doc_type], file: $db_path";
            }
            $check->close();

            if (!move_uploaded_file($tmp_name, $server_path)) {
                throw new Exception("Failed to move uploaded file for $doc_type");
            }

            if (!$stmt->execute()) {
                throw new Exception("Failed to save document $doc_type: " . $stmt->error);
            }
            $stmt->close();

            // Log action
            $stmtLog = $conn->prepare("INSERT INTO logs (admin_id, action, visible_to) VALUES (?, ?, 'all')");
            $stmtLog->bind_param("is", $_SESSION['admin_id'], $logDocAction);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    // ================================
    // Log field-level changes
    // ================================
    $admin_id = $_SESSION['admin_id'];
    $visible_to = $basic_salary !== null ? 'superadmin' : 'all';

$newData = [
    'employee_code' => $employee_code,
    'first_name' => $first_name,
    'middle_name' => $middle_name,
    'last_name' => $last_name,
    'gender' => $gender,
    'birthdate' => $birthdate,
    'contact_no' => $contact_no,
    'present_address' => $present_address,
    'permanent_address' => $permanent_address,
    'emergency_name' => $emergency_name,
    'emergency_no' => $emergency_no,
    'position' => $position,
    'department' => $department,
    'employment_status' => $employment_status,
    'ss_number' => $ss_number,
    'tin_number' => $tin_number,
    'philhealth_number' => $philhealth_number,
    'hmdf_number' => $hmdf_number
];

// Add status-specific separation dates
if ($employment_status === 'resigned') {
    $newData['date_resigned'] = $date_resigned;
    $newData['date_of_separation'] = $date_of_separation;
} elseif ($employment_status === 'retired') {
    $newData['date_retired'] = $date_retired;
    $newData['date_of_separation'] = $date_of_separation;
} elseif ($employment_status === 'fired') {
    $newData['date_fired'] = $date_fired;
    $newData['date_of_separation'] = $date_of_separation;
}

if ($basic_salary !== null) {
    $newData['basic_salary'] = $basic_salary;
}


    foreach ($newData as $field => $newValue) {
        $oldValue = $oldData[$field] ?? null;
        if ($newValue != $oldValue) {
            $logChange = "Field '$field' changed from '" . ($oldValue ?? '[empty]') . "' to '" . ($newValue ?? '[empty]') . "' for employee ID $id";
            $stmtLog = $conn->prepare("INSERT INTO logs (admin_id, action, visible_to) VALUES (?, ?, ?)");
            $stmtLog->bind_param("iss", $admin_id, $logChange, $visible_to);
            $stmtLog->execute();
            $stmtLog->close();
        }
    }

    // ================================
    // Log the update summary + privacy
    // ================================
    $action = sprintf(
        "Updated employee (Code: %s, Name: %s %s, ID: %d, Status: %s%s%s)",
        $employee_code, $first_name, $last_name, $id, $employment_status,
        $date_resigned ? ", Date Resigned: $date_resigned" : "",
        ($basic_salary !== null ? ", Basic Salary: $basic_salary" : "")
    );

    $stmt = $conn->prepare("INSERT INTO logs (admin_id, action, visible_to) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $action, $visible_to);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header("Location: manage_employee.php?status=success");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating employee ID $id: " . $e->getMessage());
    die("An error occurred while updating the employee: " . htmlspecialchars($e->getMessage()));
}
?>
