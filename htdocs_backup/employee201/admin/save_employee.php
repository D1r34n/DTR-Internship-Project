<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php';



// =============================
// 1. Validate & Capture Inputs
// =============================
$employee_code     = trim(filter_input(INPUT_POST, 'employee_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$first_name        = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$middle_name       = trim(filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$last_name         = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$gender            = trim(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$birthdate         = trim($_POST['birthdate'] ?? '');
$email             = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$contact_no        = trim(filter_input(INPUT_POST, 'contact_no', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$present_address   = trim(filter_input(INPUT_POST, 'present_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$permanent_address = trim(filter_input(INPUT_POST, 'permanent_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$emergency_name    = trim(filter_input(INPUT_POST, 'emergency_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$emergency_no      = trim(filter_input(INPUT_POST, 'emergency_no', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$position          = trim(filter_input(INPUT_POST, 'position', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$department        = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$date_hired        = trim($_POST['date_hired'] ?? '');
$ss_number         = trim(filter_input(INPUT_POST, 'ss_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$tin_number        = trim(filter_input(INPUT_POST, 'tin_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$philhealth_number = trim(filter_input(INPUT_POST, 'philhealth_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$hmdf_number       = trim(filter_input(INPUT_POST, 'hmdf_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

// Validate required fields
$required_fields = [
    'employee_code', 'first_name', 'last_name', 'gender', 'position', 'department',
    'date_hired', 'present_address', 'permanent_address', 'contact_no',
    'emergency_name', 'emergency_no', 'ss_number', 'tin_number', 'philhealth_number', 'hmdf_number'
];
foreach ($required_fields as $field) {
    if (empty($$field)) {
        die("Error: Missing required field '$field'.");
    }
}

// Validate birthdate format
if ($birthdate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
    die("Invalid birthdate format. Expected YYYY-MM-DD.");
}

// =============================
// 2. Role-based salary handling
// =============================
$basic_salary = null;
if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin') {
    $basic_salary_raw = str_replace(',', '', trim($_POST['basic_salary'] ?? ''));
    if ($basic_salary_raw !== '' && is_numeric($basic_salary_raw)) {
        $basic_salary = (float)$basic_salary_raw;
    }
}

// =============================
// 3. Handle ID Picture Upload
// =============================
$id_picture = null;
if (!empty($_FILES['id_picture']['name']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
    $allowed_ext = ['jpg', 'jpeg', 'png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $ext = strtolower(pathinfo($_FILES['id_picture']['name'], PATHINFO_EXTENSION));
    $size = $_FILES['id_picture']['size'];

    if (!in_array($ext, $allowed_ext)) {
        die("Invalid ID picture format. Allowed: JPG, JPEG, PNG.");
    }
    if ($size > $max_size) {
        die("ID picture exceeds 5MB limit.");
    }

    $uploadDir = __DIR__ . '/../Uploads/id_picture/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $serverPath = $uploadDir . $filename;
    $dbPath = 'Uploads/id_picture/' . $filename;

    if (move_uploaded_file($_FILES['id_picture']['tmp_name'], $serverPath)) {
        $id_picture = $dbPath;
    }
}

// =============================
// 4. Check for duplicate employee_code
// =============================
$stmt = $conn->prepare("SELECT id FROM employees WHERE employee_code = ?");
$stmt->bind_param("s", $employee_code);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    die("Error: Employee code already exists. Please use a unique code.");
}
$stmt->close();

// =============================
// 5. Generate login credentials
// =============================
$username = strtolower(preg_replace('/\s+/', '', $last_name)) . $employee_code;
$plain_password = bin2hex(random_bytes(4)); // random 8-char
$hashedPassword = password_hash($plain_password, PASSWORD_DEFAULT);

// =============================
// 6. Document Type Map (standardized)
// =============================
$documentMap = [
    "birth_cert"        => "Birth Certificate",
    "sss"               => "SSS",
    "tin"               => "TIN",
    "philhealth"        => "PhilHealth",
    "pagibig"           => "Pag-IBIG",
    "resume"            => "Resume",
    "contract"          => "Employment Contract",
    "policy"            => "Signed Policies",
    "medical_clearance" => "Medical Clearance",
    "memo"              => "Memo",
    "incident_report"   => "Incident Report",
    "disciplinary_action"=> "Disciplinary Action",
    "commendation"      => "Commendation",
    "exit_letter"       => "Exit Letter",
    "interview"         => "Exit Interview",
    "clearance"         => "Clearance Form"
];

// =============================
// 7. Start Transaction
// =============================
$conn->begin_transaction();

try {
    // Insert employee
    $stmt = $conn->prepare("
        INSERT INTO employees (
            employee_code, first_name, middle_name, last_name, username, plain_password, gender, id_picture,
            position, birthdate, email, present_address, permanent_address, contact_no,
            emergency_name, emergency_no, department, date_hired, basic_salary, ss_number, tin_number, philhealth_number, hmdf_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssssssssssssssssssss",
        $employee_code, $first_name, $middle_name, $last_name, $username, $plain_password, $gender, $id_picture,
        $position, $birthdate, $email, $present_address, $permanent_address, $contact_no,
        $emergency_name, $emergency_no, $department, $date_hired, $basic_salary, $ss_number, $tin_number, $philhealth_number, $hmdf_number
    );
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert employee: " . $stmt->error);
    }
    $employee_id = $stmt->insert_id;
    $stmt->close();

    // Insert employee login
    $stmt = $conn->prepare("INSERT INTO employee_users (employee_code, username, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $employee_code, $username, $hashedPassword);
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert employee login: " . $stmt->error);
    }
    $stmt->close();

 // =============================
// Upload documents
// =============================
$allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
$max_size = 5 * 1024 * 1024; // 5MB
if (!empty($_POST['document_type']) && !empty($_FILES['document_file'])) {
    foreach ($_POST['document_type'] as $i => $docTypeRaw) {
        if (empty($docTypeRaw) || empty($_FILES['document_file']['name'][$i])) continue;

        // Normalize the doc type (make lowercase, replace spaces/underscores)
        $docKey = strtolower(str_replace([' ', '-'], '_', $docTypeRaw));

        if (!array_key_exists($docKey, $documentMap)) continue; // skip invalid

        $ext = strtolower(pathinfo($_FILES['document_file']['name'][$i], PATHINFO_EXTENSION));
        $size = $_FILES['document_file']['size'][$i];
        if (!in_array($ext, $allowed_ext) || $size > $max_size) continue;

        $uploadDir = __DIR__ . '/../Uploads/' . $docKey . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $serverPath = $uploadDir . $filename;
        $dbPath = 'Uploads/' . $docKey . '/' . $filename;

        if (!move_uploaded_file($_FILES['document_file']['tmp_name'][$i], $serverPath)) {
            throw new Exception("Failed to upload document: " . $documentMap[$docKey]);
        }

        $stmt = $conn->prepare("INSERT INTO documents (employee_id, document_type, file_path) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $employee_id, $docKey, $dbPath); // save machine key
        $stmt->execute();
        $stmt->close();

        $logDocAction = "Uploaded new document [" . $documentMap[$docKey] . "], file: $dbPath";
        $stmtLog = $conn->prepare("INSERT INTO logs (admin_id, action, visible_to) VALUES (?, ?, 'all')");
        $stmtLog->bind_param("is", $_SESSION['admin_id'], $logDocAction);
        $stmtLog->execute();
        $stmtLog->close();
    }
}


    // =============================
    // Logs
    // =============================
    $admin_id = $_SESSION['admin_id'];
    $visible_to = ($basic_salary !== null) ? 'superadmin' : 'all';

    $action = "Added new employee (Code: $employee_code, Name: $first_name $last_name, Position: $position, Department: $department)";
    $stmt = $conn->prepare("INSERT INTO logs (admin_id, action, visible_to) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $action, $visible_to);
    $stmt->execute();
    $stmt->close();


    $conn->commit();

    // Save login credentials in session for modal
    $_SESSION['loginCredentials'] = [
        'username' => $username,
        'password' => $plain_password
    ];

    header("Location: add_employee.php?success=1");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error saving new employee: " . $e->getMessage());
    die("An error occurred while saving the employee: " . htmlspecialchars($e->getMessage()));
}
?>
