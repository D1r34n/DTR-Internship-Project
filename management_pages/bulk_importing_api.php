<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require '../vendor/autoload.php';
require '../db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/* =========================================================
   HELPERS
========================================================= */

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function hasCutoffOverlap(PDO $pdo, string $start, string $end, ?int $ignoreId = null): bool
{
    $sql = "SELECT COUNT(*) FROM cutoffs WHERE start_date <= :end AND end_date >= :start";
    if ($ignoreId) $sql .= " AND id != :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end',   $end);
    if ($ignoreId) $stmt->bindValue(':id', $ignoreId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchColumn() > 0;
}

function parseExcelDate(mixed $value): ?string
{
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) {
        try {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)
                ->format('Y-m-d');
        } catch (\Exception) { return null; }
    }
    $v = trim((string)$value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    try { return (new \DateTime($v))->format('Y-m-d'); } catch (\Exception) { return null; }
}

/** Apply the standard green header style to a range. */
function applyHeaderStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '97BE41']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '6A9E2B']]],
    ]);
}

/** Apply alternating sample-row styles. */
function applySampleRowStyle(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    string $range,
    bool $even = false
): void {
    $sheet->getStyle($range)->applyFromArray([
        'fill'    => ['fillType' => 'solid', 'startColor' => ['rgb' => $even ? 'FFF8E1' : 'F0F7E6']],
        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']]],
    ]);
}

/* =========================================================
   AUTH — shared role check
   Cutoff endpoints additionally require superadmin (checked inline).
========================================================= */

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['superadmin', 'admin', 'manager'])) {
    respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);
}

$myRole     = $_SESSION['user_role'];
$myDeptId   = (int)($_SESSION['department_id'] ?? 0);
$deptScoped = ($myRole === 'manager' && $myDeptId > 0);

/* =========================================================
   ENSURE cutoffs TABLE EXISTS
========================================================= */

$pdo->exec("CREATE TABLE IF NOT EXISTS `cutoffs` (
    `id`         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `start_date` date NOT NULL,
    `end_date`   date NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

/* =========================================================
   BATCH-VALIDATE EMPLOYEE IDs (shared by schedule & leaves)
========================================================= */

function fetchValidEmployeeIds(PDO $pdo, array $empIds, bool $deptScoped, int $myDeptId): array
{
    if (empty($empIds)) return [];
    
    $empIds = array_filter(array_unique(array_map(
        fn($id) => str_pad(trim((string)$id), 6, '0', STR_PAD_LEFT),
        $empIds
    )));

    $placeholders = implode(',', array_fill(0, count($empIds), '?'));

    if ($deptScoped) {
        $stmt = $pdo->prepare(
            "SELECT id, employee_id FROM employees WHERE employee_id IN ($placeholders) AND department_id = ?"
        );
        $stmt->execute([...array_values($empIds), $myDeptId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, employee_id FROM employees WHERE employee_id IN ($placeholders)");
        $stmt->execute(array_values($empIds));
    }

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $map[$r['employee_id']] = $r['id'];
    }
    return $map;
}

/* =========================================================
   DOWNLOAD SCHEDULE TEMPLATE
========================================================= */

if ($action === 'download_schedule_template') {
    ob_end_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $headers = [
        'A' => 'Employee ID',
        'B' => 'Employee Name',
        'C' => 'Start Date',
        'D' => 'End Date',
        'E' => 'Time',
    ];

    $notes = [
        'A' => 'Enter numeric employee IDs. Excel will automatically display leading zeros (e.g. 1 → 000001).',
        'B' => 'Optional — for reference only.',
        'C' => 'Format: YYYY-MM-DD (e.g. 2026-05-01).',
        'D' => 'Format: YYYY-MM-DD. Same as Start Date for a single day.',
        'E' => 'Format: HH:MM-HH:MM (e.g. 08:00-17:00).',
    ];

    // ===== HEADERS =====
    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '1', $label);
    }

    applyHeaderStyle($sheet, 'A1:E1');
    $sheet->getRowDimension(1)->setRowHeight(22);

    // ===== COMMENTS =====
    foreach ($notes as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('220pt')->setHeight('55pt');
    }

    // ===== EMPLOYEE ID FORMAT =====
    // Auto-display leading zeros (e.g. 1 => 000001)
    $sheet->getStyle('A2:A1000')
        ->getNumberFormat()
        ->setFormatCode('000000');

    // ===== SAMPLE ROWS =====
    $sheet->setCellValue('A2', 1);
    $sheet->setCellValue('B2', 'John Doe');
    $sheet->setCellValue('C2', '2026-05-01');
    $sheet->setCellValue('D2', '2026-05-01');
    $sheet->setCellValue('E2', '08:00-17:00');

    $sheet->setCellValue('A3', 2);
    $sheet->setCellValue('B3', 'Jane Doe');
    $sheet->setCellValue('C3', '2026-05-02');
    $sheet->setCellValue('D3', '2026-05-02');
    $sheet->setCellValue('E3', '');

    // ===== SAMPLE ROW STYLES =====
    applySampleRowStyle($sheet, 'A2:E2');
    applySampleRowStyle($sheet, 'A3:E3', true);

    // ===== FREEZE HEADER =====
    $sheet->freezePane('A2');

    // ===== AUTO SIZE =====
    foreach (array_keys($headers) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    // ===== OUTPUT =====
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="schedule_template.xlsx"');
    header('Cache-Control: max-age=0');

    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))
        ->save('php://output');

    exit;
}

/* =========================================================
   IMPORT SCHEDULE
========================================================= */

if ($action === 'import_schedule') {
    try {
        if (empty($_FILES['schedule_file']) || $_FILES['schedule_file']['error'] !== UPLOAD_ERR_OK) {
            respond(['status' => 'error', 'message' => 'No file uploaded or upload failed.'], 400);
        }

        $rows = IOFactory::load($_FILES['schedule_file']['tmp_name'])
            ->getActiveSheet()->toArray();
        array_shift($rows);

        $validIds = fetchValidEmployeeIds(
            $pdo,
            array_filter(array_unique(array_column($rows, 0))),
            $deptScoped,
            $myDeptId
        );

        $upsertStmt = $pdo->prepare("
            INSERT INTO schedules
                (employee_id, schedule_date, scheduled_start, scheduled_end, request_type)
            VALUES
                (:employee_id, :schedule_date, :start, :end, 'added')
            ON DUPLICATE KEY UPDATE
                scheduled_start = VALUES(scheduled_start),
                scheduled_end   = VALUES(scheduled_end),
                request_type    = 'edit'
        ");

        $pdo->beginTransaction();

        $inserted = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) continue;

            $employeeId = str_pad(trim((string)($row[0] ?? '')), 6, '0', STR_PAD_LEFT);
            $startDate  = trim((string)($row[2] ?? ''));
            $endDate    = trim((string)($row[3] ?? ''));
            $time       = trim((string)($row[4] ?? ''));

            if ($employeeId === '000000' || $startDate === '' || $endDate === '') {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing required fields'];
                continue;
            }

            if (!isset($validIds[$employeeId])) {
                $errors[] = ['row' => $rowNum, 'message' => $deptScoped
                    ? "Employee ID $employeeId not found or not in your department"
                    : "Employee ID $employeeId not found"];
                continue;
            }

            $current = strtotime($startDate);
            $end     = strtotime($endDate);

            if (!$current || !$end) {
                $errors[] = ['row' => $rowNum, 'message' => 'Invalid date'];
                continue;
            }

            if (empty($time) || !str_contains($time, '-')) {
                $errors[] = ['row' => $rowNum, 'message' => 'Invalid or missing time format (use HH:MM-HH:MM)'];
                continue;
            }

            [$startTime, $endTime] = array_map('trim', explode('-', $time, 2));
            $isOvernight = $endTime < $startTime;

            while ($current <= $end) {
                $date    = date('Y-m-d', $current);
                $startDT = $date . ' ' . $startTime . ':00';
                $endDT   = $isOvernight
                    ? date('Y-m-d', strtotime('+1 day', $current)) . ' ' . $endTime . ':00'
                    : $date . ' ' . $endTime . ':00';

                $upsertStmt->execute([
                    ':employee_id'   => $validIds[$employeeId],
                    ':schedule_date' => $date,
                    ':start'         => $startDT,
                    ':end'           => $endDT,
                ]);

                $inserted++;
                $current = strtotime('+1 day', $current);
            }
        }

        $pdo->commit();
        respond(['status' => 'success', 'inserted' => $inserted, 'errors' => $errors]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

/* =========================================================
   DOWNLOAD LEAVE TEMPLATE
========================================================= */

if ($action === 'download_leave_template') {
    ob_end_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $headers = [
        'A' => 'Employee ID',    'B' => 'Employee Name',
        'C' => 'Buffer Leave',   'D' => 'Vacation Leave',
        'E' => 'Sick Leave',     'F' => 'Paternity Leave',
        'G' => 'Maternity Leave','H' => 'Solo Parent Leave',
        'I' => 'Birthday Leave',
    ];

    $notes = [
        'A' => 'Enter numeric employee IDs. Excel will automatically display leading zeros (e.g. 1 → 000001).',
        'B' => 'Optional — for reference only.',
        'C' => 'Buffer leave days (default 0).',
        'D' => 'Vacation leave days (default 0).',
        'E' => 'Sick leave days (default 4).',
        'F' => 'Paternity leave days (default 7).',
        'G' => 'Maternity leave days (default 90).',
        'H' => 'Solo parent leave days (default 1).',
        'I' => 'Birthday leave days (default 1).',
    ];

    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '1', $label);
    }

    applyHeaderStyle($sheet, 'A1:I1');
    $sheet->getRowDimension(1)->setRowHeight(22);

    foreach ($notes as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('220pt')->setHeight('55pt');
    }

    // ===== EMPLOYEE ID FORMAT =====
    $sheet->getStyle('A2:A1000')
        ->getNumberFormat()
        ->setFormatCode('000000');

    // ===== SAMPLE ROWS =====
    $sheet->setCellValue('A2', 1);
    $sheet->fromArray(['John Doe', 0,  5, 4,  7, 90, 1, 1], null, 'B2');
    $sheet->setCellValue('A3', 2);
    $sheet->fromArray(['Jane Doe', 0, 10, 4,  0, 90, 1, 1], null, 'B3');

    applySampleRowStyle($sheet, 'A2:I2');
    applySampleRowStyle($sheet, 'A3:I3', true);

    $sheet->freezePane('A2');
    foreach (array_keys($headers) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="leave_template.xlsx"');
    header('Cache-Control: max-age=0');

    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

/* =========================================================
   IMPORT LEAVES
========================================================= */

if ($action === 'import_leaves') {
    try {
        if (empty($_FILES['schedule_file']) || $_FILES['schedule_file']['error'] !== UPLOAD_ERR_OK) {
            respond(['status' => 'error', 'message' => 'No file uploaded or upload failed.'], 400);
        }

        $rows = IOFactory::load($_FILES['schedule_file']['tmp_name'])
            ->getActiveSheet()->toArray();
        array_shift($rows);

        $validIds = fetchValidEmployeeIds(
            $pdo,
            array_filter(array_unique(array_column($rows, 0))),
            $deptScoped,
            $myDeptId
        );

        $upsertStmt = $pdo->prepare("
            INSERT INTO employee_leave_balances
                (employee_id, buffer_leave, vacation_leave, sick_leave,
                 paternity_leave, maternity_leave, solo_parent_leave, birthday_leave)
            VALUES
                (:employee_id, :buffer, :vacation, :sick,
                 :paternity, :maternity, :solo_parent, :birthday)
            ON DUPLICATE KEY UPDATE
                buffer_leave      = VALUES(buffer_leave),
                vacation_leave    = VALUES(vacation_leave),
                sick_leave        = VALUES(sick_leave),
                paternity_leave   = VALUES(paternity_leave),
                maternity_leave   = VALUES(maternity_leave),
                solo_parent_leave = VALUES(solo_parent_leave),
                birthday_leave    = VALUES(birthday_leave)
        ");

        $pdo->beginTransaction();

        $inserted = 0;
        $errors   = [];
        $toInt    = fn($v, $default) => is_numeric(trim((string)$v))
            ? (int)trim((string)$v)
            : $default;

        foreach ($rows as $i => $row) {
            $rowNum     = $i + 2;

            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) continue;

            $employeeId = str_pad(trim((string)($row[0] ?? '')), 6, '0', STR_PAD_LEFT);

            if ($employeeId === '000000') {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing employee ID'];
                continue;
            }

            if (!isset($validIds[$employeeId])) {
                $errors[] = ['row' => $rowNum, 'message' => $deptScoped
                    ? "Employee ID $employeeId not found or not in your department"
                    : "Employee ID $employeeId not found"];
                continue;
            }

            $upsertStmt->execute([
                ':employee_id' => $validIds[$employeeId],
                ':buffer'      => $toInt($row[2] ?? '', 0),
                ':vacation'    => $toInt($row[3] ?? '', 0),
                ':sick'        => $toInt($row[4] ?? '', 4),
                ':paternity'   => $toInt($row[5] ?? '', 7),
                ':maternity'   => $toInt($row[6] ?? '', 90),
                ':solo_parent' => $toInt($row[7] ?? '', 1),
                ':birthday'    => $toInt($row[8] ?? '', 1),
            ]);

            $inserted++;
        }

        $pdo->commit();
        respond(['status' => 'success', 'inserted' => $inserted, 'errors' => $errors]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

/* =========================================================
   DOWNLOAD EMPLOYEE TEMPLATE
========================================================= */

if ($action === 'download_employee_template') {
    if ($myRole !== 'superadmin') respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);
    ob_end_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $headers = [
        'A' => 'Employee ID',
        'B' => 'First Name',
        'C' => 'Last Name',
        'D' => 'Email',
        'E' => 'Birthdate',
        'F' => 'Role',
        'G' => 'Department Code',
    ];

    $notes = [
        'A' => 'Enter numeric employee IDs. Excel will automatically display leading zeros (e.g. 1 → 000001). Must be exactly 6 digits and unique.',
        'B' => 'Employee first name (required).',
        'C' => 'Employee last name (required).',
        'D' => 'Unique email address (required).',
        'E' => 'Format: YYYY-MM-DD (e.g. 1995-04-25). Required.',
        'F' => 'Role key: employee, manager, workforce, admin (required).',
        'G' => 'Department code as shown in the Departments page (optional).',
    ];

    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '1', $label);
    }

    applyHeaderStyle($sheet, 'A1:G1');
    $sheet->getRowDimension(1)->setRowHeight(22);

    foreach ($notes as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('240pt')->setHeight('65pt');
    }

    // Employee ID leading-zero format
    $sheet->getStyle('A2:A1000')
        ->getNumberFormat()
        ->setFormatCode('000000');

    // Sample rows
    $sheet->setCellValue('A2', 1);
    $sheet->fromArray(['Juan', 'Dela Cruz', 'juan.delacruz@company.com', '1995-04-25', 'employee', 'HR'], null, 'B2');

    $sheet->setCellValue('A3', 2);
    $sheet->fromArray(['Maria', 'Santos', 'maria.santos@company.com', '1990-11-12', 'manager', 'IT'], null, 'B3');

    applySampleRowStyle($sheet, 'A2:G2');
    applySampleRowStyle($sheet, 'A3:G3', true);

    $sheet->freezePane('A2');

    foreach (array_keys($headers) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="employee_import_template.xlsx"');
    header('Cache-Control: max-age=0');

    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

/* =========================================================
   IMPORT EMPLOYEES
========================================================= */

if ($action === 'import_employees') {
    if ($myRole !== 'superadmin') respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);

    try {
        if (empty($_FILES['employees_file']) || $_FILES['employees_file']['error'] !== UPLOAD_ERR_OK) {
            respond(['status' => 'error', 'message' => 'No file uploaded or upload failed.'], 400);
        }

        $rows = IOFactory::load($_FILES['employees_file']['tmp_name'])
            ->getActiveSheet()->toArray();
        array_shift($rows); // remove header

        // Pre-load all role keys → IDs
        $roleMap = [];
        foreach ($pdo->query("SELECT id, role_key FROM roles")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roleMap[strtolower($r['role_key'])] = (int)$r['id'];
        }

        // Pre-load all department codes → IDs
        $deptMap = [];
        foreach ($pdo->query("SELECT id, department_code FROM departments")->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $deptMap[strtoupper(trim($d['department_code']))] = (int)$d['id'];
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO employees
                (employee_id, first_name, last_name, email, role_id, department_id,
                 birthdate, hired_date, profile_image, password)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'default_profile.png', 'HSN.123')
        ");

        $leaveStmt = $pdo->prepare("INSERT INTO employee_leave_balances (employee_id) VALUES (?)");

        $logStmt = $pdo->prepare("
            INSERT INTO logs (employee_id, log_type, log_time, longitude, latitude, is_within_office, edit_requested_by)
            VALUES (?, 'ADD_EMPLOYEE', NOW(), 0, 0, 0, ?)
        ");

        $pdo->beginTransaction();

        $inserted = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) continue;

            $employeeId  = str_pad(trim((string)($row[0] ?? '')), 6, '0', STR_PAD_LEFT);
            $firstName   = trim((string)($row[1] ?? ''));
            $lastName    = trim((string)($row[2] ?? ''));
            $email       = trim((string)($row[3] ?? ''));
            $birthdate   = parseExcelDate($row[4] ?? null);
            $roleKey     = strtolower(trim((string)($row[5] ?? '')));
            $deptCode    = strtoupper(trim((string)($row[6] ?? '')));

            // Validate required fields
            if ($employeeId === '000000' || $firstName === '' || $lastName === '' || $email === '') {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing required fields (ID, First Name, Last Name, Email)'];
                continue;
            }

            if ($birthdate === null) {
                $errors[] = ['row' => $rowNum, 'message' => 'Invalid or missing birthdate (use YYYY-MM-DD)'];
                continue;
            }

            if (!preg_match('/^\d{6}$/', $employeeId)) {
                $errors[] = ['row' => $rowNum, 'message' => "Employee ID \"$employeeId\" must be exactly 6 digits"];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNum, 'message' => "Invalid email address \"$email\""];
                continue;
            }

            if (!isset($roleMap[$roleKey])) {
                $errors[] = ['row' => $rowNum, 'message' => "Unknown role \"$roleKey\". Valid roles: " . implode(', ', array_keys($roleMap))];
                continue;
            }

            // Duplicate employee ID check
            $dupId = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ?");
            $dupId->execute([$employeeId]);
            if ($dupId->fetchColumn()) {
                $errors[] = ['row' => $rowNum, 'message' => "Employee ID $employeeId already exists"];
                continue;
            }

            // Duplicate email check
            $dupEmail = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?)");
            $dupEmail->execute([$email]);
            if ($dupEmail->fetchColumn()) {
                $errors[] = ['row' => $rowNum, 'message' => "Email \"$email\" already exists"];
                continue;
            }

            $roleId = $roleMap[$roleKey];
            $deptId = ($deptCode !== '' && isset($deptMap[$deptCode])) ? $deptMap[$deptCode] : null;

            $insertStmt->execute([
                $employeeId, $firstName, $lastName, $email,
                $roleId, $deptId, $birthdate,
            ]);

            $newId = (int)$pdo->lastInsertId();
            $leaveStmt->execute([$newId]);
            $logStmt->execute([$newId, $_SESSION['user_id']]);

            $inserted++;
        }

        $pdo->commit();
        respond(['status' => 'success', 'inserted' => $inserted, 'errors' => $errors]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

/* =========================================================
   CUTOFF CRUD  (superadmin only)
========================================================= */

// All cutoff routes require superadmin
if (str_starts_with($action, 'cutoff') || in_array($action, ['download_cutoff_template', 'import_cutoffs'])
    || (!$action && in_array($method, ['GET', 'POST', 'PUT', 'DELETE']))
) {
    if ($myRole !== 'superadmin') {
        respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);
    }
}

// ── Download cutoff template ──────────────────────────────────

if ($action === 'download_cutoff_template') {
    ob_end_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Start Date');
    $sheet->setCellValue('B1', 'End Date');

    applyHeaderStyle($sheet, 'A1:B1');
    $sheet->getRowDimension(1)->setRowHeight(22);

    foreach ([
        'A' => 'Format: DD-Mon-YYYY (e.g. 01-May-2026).',
        'B' => 'Format: DD-Mon-YYYY. Must be on or after Start Date.',
    ] as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('200pt')->setHeight('50pt');
    }

    $sheet->setCellValue('A2', '01-May-2026');
    $sheet->setCellValue('B2', '15-May-2026');
    $sheet->setCellValue('A3', '16-May-2026');
    $sheet->setCellValue('B3', '31-May-2026');

    applySampleRowStyle($sheet, 'A2:B2');
    applySampleRowStyle($sheet, 'A3:B3', true);

    foreach (['A', 'B'] as $col) {
        $sheet->getColumnDimension($col)->setWidth(16);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="cutoff_template.xlsx"');
    header('Cache-Control: max-age=0');

    (IOFactory::createWriter($spreadsheet, 'Xlsx'))->save('php://output');
    exit;
}

// ── Bulk import cutoffs ───────────────────────────────────────

if ($action === 'import_cutoffs' && $method === 'POST') {
    if ($myRole !== 'superadmin') respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);

    if (empty($_FILES['cutoff_file']['tmp_name'])) {
        respond(['status' => 'error', 'message' => 'No file uploaded.'], 422);
    }

    try {
        $rows = IOFactory::load($_FILES['cutoff_file']['tmp_name'])
            ->getActiveSheet()->toArray(null, false, false);
    } catch (\Exception) {
        respond(['status' => 'error', 'message' => 'Could not read file.'], 422);
    }

    $inserted = 0;
    $errors   = [];
    $stmt     = $pdo->prepare("INSERT INTO cutoffs (start_date, end_date) VALUES (?, ?)");

    foreach (array_slice($rows, 1) as $i => $row) {
        $rowNum = $i + 2;
        $start  = parseExcelDate($row[0] ?? null);
        $end    = parseExcelDate($row[1] ?? null);

        if ($start === null && $end === null) continue;

        if (!$start || !$end) {
            $errors[] = ['row' => $rowNum, 'message' => 'Missing or invalid date'];
            continue;
        }
        if ($start > $end) {
            $errors[] = ['row' => $rowNum, 'message' => 'Start date is after end date'];
            continue;
        }
        if (hasCutoffOverlap($pdo, $start, $end)) {
            $errors[] = ['row' => $rowNum, 'message' => 'Overlaps with existing cut-off'];
            continue;
        }

        try {
            $stmt->execute([$start, $end]);
            $inserted++;
        } catch (\Exception) {
            $errors[] = ['row' => $rowNum, 'message' => 'Database error'];
        }
    }

    respond(['status' => 'success', 'inserted' => $inserted, 'errors' => $errors]);
}

// ── Cutoff REST (GET / POST / PUT / DELETE, no ?action) ───────

if (!$action) {
    if ($myRole !== 'superadmin') respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, start_date, end_date FROM cutoffs ORDER BY start_date DESC");
        respond($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($method === 'POST') {
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $start = trim($data['start_date'] ?? '');
        $end   = trim($data['end_date']   ?? '');

        if (!$start || !$end) respond(['error' => 'start_date and end_date are required'], 422);
        if ($start > $end)    respond(['error' => 'start_date must not be after end_date'], 422);
        if (hasCutoffOverlap($pdo, $start, $end)) respond(['error' => 'Cut-off overlaps with an existing period'], 409);

        $stmt = $pdo->prepare("INSERT INTO cutoffs (start_date, end_date) VALUES (?, ?)");
        $stmt->execute([$start, $end]);

        respond(['id' => (int)$pdo->lastInsertId(), 'start_date' => $start, 'end_date' => $end], 201);
    }

    if ($method === 'PUT') {
        $id    = (int)($_GET['id'] ?? 0);
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $start = trim($data['start_date'] ?? '');
        $end   = trim($data['end_date']   ?? '');

        if (!$id || !$start || !$end) respond(['error' => 'id, start_date and end_date are required'], 422);
        if ($start > $end)            respond(['error' => 'start_date must not be after end_date'], 422);
        if (hasCutoffOverlap($pdo, $start, $end, $id)) respond(['error' => 'Updated range overlaps with another cut-off period'], 409);

        $pdo->prepare("UPDATE cutoffs SET start_date = ?, end_date = ? WHERE id = ?")
            ->execute([$start, $end, $id]);

        respond(['id' => $id, 'start_date' => $start, 'end_date' => $end]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) respond(['error' => 'id is required'], 422);

        $pdo->prepare("DELETE FROM cutoffs WHERE id = ?")->execute([$id]);
        respond(['success' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
}

respond(['status' => 'error', 'message' => 'Invalid action'], 400);