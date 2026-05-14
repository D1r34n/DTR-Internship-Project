<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../auth/session_check.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/config.php'; // provides $conn (mysqli)
require_once __DIR__ . '/../includes/log.php';     // provides logAction()

// ─── Redirect helper ──────────────────────────────────────────────────────────
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>window.location.href='$url';</script>";
    }
    exit;
}

// ─── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['import_data'])) {
    redirect('import_employees.php');
}

$importData = json_decode($_POST['import_data'], true);
if (!$importData || !is_array($importData) || count($importData) === 0) {
    $_SESSION['import_error'] = 'No valid data received. Please try again.';
    redirect('import_employees.php');
}

// ─── Password generator ───────────────────────────────────────────────────────
// Format: 7 random digits + 1 random uppercase letter = 8 characters
// Example: 4829301A
function generatePassword() {
    $digits = '';
    for ($i = 0; $i < 7; $i++) {
        $digits .= random_int(0, 9);
    }
    $letter = chr(random_int(65, 90)); // A-Z
    return $digits . $letter;
}

// ─── Username builder ─────────────────────────────────────────────────────────
// Format: lastname (lowercase, no spaces) + employee_code
// Example: delacruze0922250311
function buildUsername($last_name, $employee_code) {
    $lastName = strtolower(preg_replace('/\s+/', '', (string)$last_name));
    return $lastName . $employee_code;
}

// ─── Prepare employee insert/update statement ─────────────────────────────────
$sql = "
    INSERT INTO employees (
        employee_code, first_name, middle_name, last_name,
        gender, position, birthdate, email, contact_no, department,
        date_hired, ss_number, hmdf_number, philhealth_number, tin_number,
        present_address, permanent_address, emergency_name, emergency_no
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        first_name        = VALUES(first_name),
        middle_name       = VALUES(middle_name),
        last_name         = VALUES(last_name),
        gender            = VALUES(gender),
        position          = VALUES(position),
        birthdate         = VALUES(birthdate),
        email             = VALUES(email),
        contact_no        = VALUES(contact_no),
        department        = VALUES(department),
        date_hired        = VALUES(date_hired),
        ss_number         = VALUES(ss_number),
        hmdf_number       = VALUES(hmdf_number),
        philhealth_number = VALUES(philhealth_number),
        tin_number        = VALUES(tin_number),
        present_address   = VALUES(present_address),
        emergency_name    = VALUES(emergency_name),
        emergency_no      = VALUES(emergency_no)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['import_error'] = 'DB prepare failed: ' . $conn->error;
    redirect('import_employees.php');
}

// ─── Process each row ─────────────────────────────────────────────────────────
$inserted    = 0;
$updated     = 0;
$failed      = 0;
$errors      = [];
$credentials = []; // plaintext credentials collected for CSV download

foreach ($importData as $index => $row) {
    $rowNum = $index + 1;

    $employee_code     = sanitize($row['employee_code']     ?? '');
    $first_name        = sanitize($row['first_name']        ?? '');
    $middle_name       = sanitize($row['middle_name']       ?? '');
    $last_name         = sanitize($row['last_name']         ?? '');
    $gender            = sanitize($row['gender']            ?? '');
    $position          = sanitize($row['position']          ?? '');
    $birthdate         = fmtDate($row['birthdate']          ?? '');
    $email             = sanitize($row['email']             ?? '');
    $contact_no        = sanitize($row['contact_no']        ?? '');
    $department        = sanitize($row['department']        ?? '');
    $date_hired        = fmtDate($row['date_hired']         ?? '');
    $ss_number         = sanitize($row['ss_number']         ?? '');
    $hmdf_number       = sanitize($row['hmdf_number']       ?? '');
    $philhealth_number = sanitize($row['philhealth_number'] ?? '');
    $tin_number        = sanitize($row['tin_number']        ?? '');
    $present_address   = sanitize($row['present_address']   ?? '');
    $emergency_name    = sanitize($row['emergency_name']    ?? '');
    $emergency_no      = sanitize($row['emergency_no']      ?? '');

    if (empty($employee_code) || empty($first_name)) {
        $failed++;
        $errors[] = "Row $rowNum skipped: missing employee code or first name.";
        continue;
    }

    // ── Check if employee already exists ──────────────────────────────────────
    $check = $conn->prepare("SELECT id FROM employees WHERE employee_code = ? LIMIT 1");
    $check->bind_param("s", $employee_code);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0;
    $check->close();

    // ── Insert or update employee record ──────────────────────────────────────
    $stmt->bind_param(
        "sssssssssssssssssss",
        $employee_code, $first_name, $middle_name, $last_name,
        $gender, $position, $birthdate, $email, $contact_no, $department,
        $date_hired, $ss_number, $hmdf_number, $philhealth_number, $tin_number,
        $present_address, $present_address,
        $emergency_name, $emergency_no
    );

    if (!$stmt->execute()) {
        $failed++;
        $errors[] = "Row $rowNum ({$employee_code}): " . $stmt->error;
        continue;
    }

    $exists ? $updated++ : $inserted++;

    // ── Generate login credentials only for NEW employees ─────────────────────
    if (!$exists) {
        $username = buildUsername($last_name, $employee_code);

        // Ensure username is unique in employee_users
        $uCheck = $conn->prepare("SELECT id FROM employee_users WHERE username = ? LIMIT 1");
        if ($uCheck) {
            $uCheck->bind_param("s", $username);
            $uCheck->execute();
            $uCheck->store_result();
            if ($uCheck->num_rows > 0) {
                $suffix   = 2;
                $baseUser = $username;
                do {
                    $username = $baseUser . '_' . $suffix++;
                    $uCheck->bind_param("s", $username);
                    $uCheck->execute();
                    $uCheck->store_result();
                } while ($uCheck->num_rows > 0);
            }
            $uCheck->close();
        }

        // 7 digits + 1 uppercase letter (e.g. 4829301A)
        $plainPassword  = generatePassword();
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);

        // Insert into employee_users table (store both hash and plaintext)
        $accStmt = $conn->prepare(
            "INSERT INTO employee_users (employee_code, username, password, plain_password, created_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                username       = VALUES(username),
                password       = VALUES(password),
                plain_password = VALUES(plain_password),
                created_at     = NOW()"
        );
        if ($accStmt) {
            $accStmt->bind_param("ssss", $employee_code, $username, $hashedPassword, $plainPassword);
            if (!$accStmt->execute()) {
                $errors[] = "Row $rowNum ({$employee_code}): account creation failed — " . $accStmt->error;
            }
            $accStmt->close();
        }

        // Collect plaintext credentials for admin download
        $credentials[] = [
            'employee_code' => $employee_code,
            'full_name'     => trim(($first_name ?? '') . ' ' . ($last_name ?? '')),
            'username'      => $username,
            'password'      => $plainPassword,
        ];
    }
}

$stmt->close();

// ─── Log the action ───────────────────────────────────────────────────────────
logAction($conn, $_SESSION['admin_id'],
    "Bulk imported employees: {$inserted} inserted, {$updated} updated, {$failed} failed."
);

// ─── Build result message ─────────────────────────────────────────────────────
$parts = [];
if ($inserted > 0) $parts[] = "$inserted new employee" . ($inserted !== 1 ? 's' : '') . " added";
if ($updated  > 0) $parts[] = "$updated employee"      . ($updated  !== 1 ? 's' : '') . " updated";
if ($failed   > 0) $parts[] = "$failed row"            . ($failed   !== 1 ? 's' : '') . " skipped";

$message = 'Import complete. ' . implode(', ', $parts) . '.';
if (!empty($errors)) {
    $message .= ' Issues: ' . implode(' | ', array_slice($errors, 0, 3));
    if (count($errors) > 3) $message .= '... and ' . (count($errors) - 3) . ' more.';
}

if ($inserted > 0 || $updated > 0) {
    $_SESSION['import_success'] = $message;
    if (!empty($credentials)) {
        $_SESSION['import_credentials'] = $credentials; // shown once, then cleared
    }
} else {
    $_SESSION['import_error'] = $message ?: 'No employees were imported.';
}

redirect('import_employees.php');

// ─── Helpers ──────────────────────────────────────────────────────────────────
function sanitize($val) {
    $val = trim(strip_tags((string)$val));
    return $val === '' ? null : $val;
}

function fmtDate($val) {
    $val = trim((string)$val);
    if (empty($val)) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
        [$y, $m, $d] = explode('-', $val);
        return checkdate((int)$m, (int)$d, (int)$y) ? $val : null;
    }
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $val, $p)) {
        $y = (int)$p[3];
        if ($y < 100) $y += $y > 30 ? 1900 : 2000;
        return checkdate((int)$p[1], (int)$p[2], $y)
            ? sprintf('%04d-%02d-%02d', $y, (int)$p[1], (int)$p[2])
            : null;
    }
    return null;
}