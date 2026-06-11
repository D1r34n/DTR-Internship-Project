<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require '../vendor/autoload.php';
require '../db.php';
require_once '../send_mail.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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
    $v = ltrim(trim((string)$value), "'");
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
   HEADER VALIDATION (shared by all importers)
========================================================= */

/**
 * Checks that the first row of the uploaded sheet matches expected column headers.
 * Returns an error message string on mismatch, or null if the headers are correct.
 */
function validateSheetHeaders(array $headerRow, array $expected): ?string
{
    foreach ($expected as $i => $label) {
        $actual = trim((string)($headerRow[$i] ?? ''));
        if (strcasecmp($actual, $label) !== 0) {
            $col = chr(65 + $i);
            $got = $actual !== '' ? "\"$actual\"" : '(empty)';
            return "Wrong template: column $col should be \"$label\" but got $got. "
                 . 'Please download and use the correct template.';
        }
    }
    return null;
}

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
        'C' => 'Format: DD-Mon-YYYY (e.g. 01-May-2026).',
        'D' => 'Format: DD-Mon-YYYY. Same as Start Date for a single day.',
        'E' => 'Format: HH:MM-HH:MM (e.g. 08:00-17:00). Use EMPTY to clear/remove the schedule for that date range.',
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

    // ===== DATE COLUMNS: force text so Excel won't convert to serial numbers =====
    $sheet->getStyle('C2:D1000')
        ->getNumberFormat()
        ->setFormatCode('@');

    // ===== SAMPLE ROWS =====
    $sheet->setCellValue('A2', 1);
    $sheet->setCellValue('B2', 'John Doe');
    $sheet->setCellValue('C2', '01-May-2026');
    $sheet->setCellValue('D2', '01-May-2026');
    $sheet->setCellValue('E2', '08:00-17:00');

    $sheet->setCellValue('A3', 2);
    $sheet->setCellValue('B3', 'Jane Doe');
    $sheet->setCellValue('C3', '02-May-2026');
    $sheet->setCellValue('D3', '02-May-2026');
    $sheet->setCellValue('E3', 'EMPTY');

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
   CHECK SCHEDULE CONFLICTS (pre-import dry-run)
========================================================= */

if ($action === 'check_schedule_conflicts') {
    if (empty($_FILES['schedule_file']) || $_FILES['schedule_file']['error'] !== UPLOAD_ERR_OK) {
        respond(['status' => 'error', 'message' => 'No file uploaded.'], 400);
    }

    $rows   = IOFactory::load($_FILES['schedule_file']['tmp_name'])
        ->getActiveSheet()->toArray();
    $header = array_shift($rows);

    if ($err = validateSheetHeaders($header, ['Employee ID', 'Employee Name', 'Start Date', 'End Date', 'Time'])) {
        respond(['status' => 'error', 'message' => $err], 422);
    }

    $validIds = fetchValidEmployeeIds(
        $pdo,
        array_filter(array_unique(array_column($rows, 0))),
        $deptScoped,
        $myDeptId
    );

    $checkStmt = $pdo->prepare("
        SELECT scheduled_start, scheduled_end, is_rest_day
        FROM schedules
        WHERE employee_id = ? AND schedule_date = ? AND is_archived = 0
    ");

    $conflicts = [];
    $total     = 0;
    $MAX_SHOW  = 50;

    foreach ($rows as $row) {
        if (empty(array_filter(array_map('trim', array_map('strval', $row))))) continue;

        $employeeId = str_pad(trim((string)($row[0] ?? '')), 6, '0', STR_PAD_LEFT);
        $empName    = trim((string)($row[1] ?? ''));
        $startDate  = parseExcelDate($row[2] ?? null);
        $endDate    = parseExcelDate($row[3] ?? null);
        $time       = trim((string)($row[4] ?? ''));
        $newTime    = strtoupper($time) === 'EMPTY' ? '(clear)' : $time;

        if (!isset($validIds[$employeeId]) || $startDate === null || $endDate === null) continue;

        $empDbId = $validIds[$employeeId];
        $current = strtotime($startDate);
        $end     = strtotime($endDate);

        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $checkStmt->execute([$empDbId, $date]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $total++;
                if (count($conflicts) < $MAX_SHOW) {
                    if ($existing['is_rest_day']) {
                        $existingTime = 'Rest Day';
                    } elseif ($existing['scheduled_start']) {
                        $existingTime = date('H:i', strtotime($existing['scheduled_start']))
                            . '-' . date('H:i', strtotime($existing['scheduled_end']));
                    } else {
                        $existingTime = '—';
                    }

                    $conflicts[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => $empName,
                        'date'          => date('d-M-Y', $current),
                        'existing'      => $existingTime,
                        'new'           => $newTime,
                    ];
                }
            }

            $current = strtotime('+1 day', $current);
        }
    }

    respond(['status' => 'success', 'conflicts' => $conflicts, 'total' => $total]);
}

/* =========================================================
   IMPORT SCHEDULE
========================================================= */

if ($action === 'import_schedule') {
    try {
        if (empty($_FILES['schedule_file']) || $_FILES['schedule_file']['error'] !== UPLOAD_ERR_OK) {
            respond(['status' => 'error', 'message' => 'No file uploaded or upload failed.'], 400);
        }

        $rows   = IOFactory::load($_FILES['schedule_file']['tmp_name'])
            ->getActiveSheet()->toArray();
        $header = array_shift($rows);

        if ($err = validateSheetHeaders($header, ['Employee ID', 'Employee Name', 'Start Date', 'End Date', 'Time'])) {
            respond(['status' => 'error', 'message' => $err], 422);
        }

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

        $deleteStmt = $pdo->prepare(
            "DELETE FROM schedules WHERE employee_id = ? AND schedule_date = ?"
        );

        $pdo->beginTransaction();

        $inserted = 0;
        $cleared  = 0;
        $errors   = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) continue;

            $employeeId = str_pad(trim((string)($row[0] ?? '')), 6, '0', STR_PAD_LEFT);
            $startDate  = parseExcelDate($row[2] ?? null);
            $endDate    = parseExcelDate($row[3] ?? null);
            $time       = trim((string)($row[4] ?? ''));

            if ($employeeId === '000000' || $startDate === null || $endDate === null) {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing or invalid date (use DD-Mon-YYYY, e.g. 07-Jan-2026)'];
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

            if ($current > $end) {
                $errors[] = ['row' => $rowNum, 'message' => "Start date $startDate is after end date $endDate"];
                continue;
            }

            $isClear     = (strtoupper($time) === 'EMPTY');
            $isOvernight = false;

            if (!$isClear) {
                if (!str_contains($time, '-')) {
                    $errors[] = ['row' => $rowNum, 'message' => 'Invalid time format — use HH:MM-HH:MM, or EMPTY to clear'];
                    continue;
                }

                [$startTime, $endTime] = array_map('trim', explode('-', $time, 2));

                if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                    $errors[] = ['row' => $rowNum, 'message' => "Invalid time \"$time\" — use HH:MM-HH:MM (e.g. 08:00-17:00)"];
                    continue;
                }

                $isOvernight = $endTime < $startTime;
            }

            while ($current <= $end) {
                $date = date('Y-m-d', $current);

                if ($isClear) {
                    $deleteStmt->execute([$validIds[$employeeId], $date]);
                    if ($deleteStmt->rowCount() > 0) $cleared++;
                } else {
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
                }

                $current = strtotime('+1 day', $current);
            }
        }

        $pdo->commit();
        respond(['status' => 'success', 'inserted' => $inserted, 'cleared' => $cleared, 'errors' => $errors]);

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

        $rows   = IOFactory::load($_FILES['schedule_file']['tmp_name'])
            ->getActiveSheet()->toArray();
        $header = array_shift($rows);

        if ($err = validateSheetHeaders($header, [
            'Employee ID', 'Employee Name',
            'Buffer Leave', 'Vacation Leave', 'Sick Leave',
            'Paternity Leave', 'Maternity Leave', 'Solo Parent Leave', 'Birthday Leave',
        ])) {
            respond(['status' => 'error', 'message' => $err], 422);
        }

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
            ? max(0, (int)trim((string)$v))
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
        'E' => 'Format: DD-Mon-YYYY (e.g. 25-Apr-1995). Required.',
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

    // Birthdate column: force text so Excel won't convert to serial numbers
    $sheet->getStyle('E2:E1000')
        ->getNumberFormat()
        ->setFormatCode('@');

    // Sample rows
    $sheet->setCellValue('A2', 1);
    $sheet->fromArray(['Juan', 'Dela Cruz', 'juan.delacruz@company.com', '25-Apr-1995', 'employee', 'HR'], null, 'B2');

    $sheet->setCellValue('A3', 2);
    $sheet->fromArray(['Maria', 'Santos', 'maria.santos@company.com', '12-Nov-1990', 'manager', 'IT'], null, 'B3');

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

        $rows   = IOFactory::load($_FILES['employees_file']['tmp_name'])
            ->getActiveSheet()->toArray();
        $header = array_shift($rows);

        if ($err = validateSheetHeaders($header, [
            'Employee ID', 'First Name', 'Last Name',
            'Email', 'Birthdate', 'Role', 'Department Code',
        ])) {
            respond(['status' => 'error', 'message' => $err], 422);
        }

        // Pre-load all role keys → IDs
        $roleMap = [];
        foreach ($pdo->query("SELECT id, role_key, role_name FROM roles")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $roleMap[strtolower($r['role_key'])] = ['id' => (int)$r['id'], 'name' => $r['role_name']];
        }

        // Pre-load all department codes → IDs + names
        $deptMap = [];
        foreach ($pdo->query("SELECT id, department_code, department_name FROM departments")->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $deptMap[strtoupper(trim($d['department_code']))] = ['id' => (int)$d['id'], 'name' => $d['department_name']];
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

        // Prepare duplicate-check statements once, outside the loop
        $dupIdStmt    = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $dupEmailStmt = $pdo->prepare("SELECT id FROM employees WHERE LOWER(email) = LOWER(?)");

        $pdo->beginTransaction();

        $inserted          = 0;
        $errors            = [];
        $insertedEmployees = [];
        $validRoles        = implode(', ', array_keys($roleMap));

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

            // Required field presence
            if ($employeeId === '000000' || $firstName === '' || $lastName === '' || $email === '') {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing required fields (ID, First Name, Last Name, Email)'];
                continue;
            }

            if (!preg_match('/^\d{6}$/', $employeeId)) {
                $errors[] = ['row' => $rowNum, 'message' => "Employee ID \"$employeeId\" must be exactly 6 digits"];
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNum, 'message' => "Invalid email \"$email\""];
                continue;
            }

            if ($birthdate === null) {
                $errors[] = ['row' => $rowNum, 'message' => 'Invalid or missing birthdate (use DD-Mon-YYYY, e.g. 25-Apr-1995)'];
                continue;
            }

            if ($roleKey === '') {
                $errors[] = ['row' => $rowNum, 'message' => "Role is required. Valid roles: $validRoles"];
                continue;
            }

            if (!isset($roleMap[$roleKey])) {
                $errors[] = ['row' => $rowNum, 'message' => "Unknown role \"$roleKey\". Valid roles: $validRoles"];
                continue;
            }

            // Duplicate employee ID check (sees own-transaction inserts too)
            $dupIdStmt->execute([$employeeId]);
            if ($dupIdStmt->fetchColumn()) {
                $errors[] = ['row' => $rowNum, 'message' => "Employee ID $employeeId already exists"];
                continue;
            }

            // Duplicate email check
            $dupEmailStmt->execute([$email]);
            if ($dupEmailStmt->fetchColumn()) {
                $errors[] = ['row' => $rowNum, 'message' => "Email \"$email\" already exists"];
                continue;
            }

            $roleId   = $roleMap[$roleKey]['id'];
            $roleName = $roleMap[$roleKey]['name'];
            $deptId   = ($deptCode !== '' && isset($deptMap[$deptCode])) ? $deptMap[$deptCode]['id']   : null;
            $deptName = ($deptCode !== '' && isset($deptMap[$deptCode])) ? $deptMap[$deptCode]['name'] : '';

            $insertStmt->execute([
                $employeeId, $firstName, $lastName, $email,
                $roleId, $deptId, $birthdate,
            ]);

            $newId = (int)$pdo->lastInsertId();

            $leaveStmt->execute([$newId]);
            $logStmt->execute([$newId, $_SESSION['user_id']]);

            $insertedEmployees[] = [
                'id'              => $newId,
                'employee_id'     => $employeeId,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'email'           => $email,
                'role'            => $roleKey,
                'role_name'       => $roleName,
                'department_id'   => $deptId ?? '',
                'department_name' => $deptName,
            ];

            $inserted++;
        }

        $pdo->commit();
        respond(['status' => 'success', 'inserted' => $inserted, 'errors' => $errors, 'inserted_employees' => $insertedEmployees]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

/* =========================================================
   SEND WELCOME EMAILS  (called async after import_employees)
========================================================= */

if ($action === 'send_welcome_emails') {
    if ($myRole !== 'superadmin') respond(['status' => 'error', 'message' => 'Unauthorized.'], 403);

    ignore_user_abort(true);

    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));

    if (empty($ids)) respond(['status' => 'success', 'sent' => 0]);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT first_name, last_name, email FROM employees WHERE id IN ($placeholders)"
    );
    $stmt->execute($ids);

    $recipients = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $emp) {
        $fullName = "{$emp['first_name']} {$emp['last_name']}";
        $email    = $emp['email'];
        $recipients[] = [
            'email' => $email,
            'name'  => $fullName,
            'body'  => "
                <p>Hi " . htmlspecialchars($fullName) . ",</p>
                <p>Your account has been created in the HSN DTR System.</p>
                <p><strong>Email:</strong> " . htmlspecialchars($email) . "<br>
                <strong>Password:</strong> HSN.123</p>
                <p>Please log in and change your password.</p>
                <p>— HSN DTR System</p>
            ",
        ];
    }

    $sent = sendMailBulk($recipients, 'Your HSN DTR Account');
    respond(['status' => 'success', 'sent' => $sent]);
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

    // Date columns: force text so Excel won't convert to serial numbers
    $sheet->getStyle('A2:B1000')
        ->getNumberFormat()
        ->setFormatCode('@');

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

    if (empty($_FILES['cutoff_file']) || $_FILES['cutoff_file']['error'] !== UPLOAD_ERR_OK) {
        respond(['status' => 'error', 'message' => 'No file uploaded or upload failed.'], 422);
    }

    try {
        $rows = IOFactory::load($_FILES['cutoff_file']['tmp_name'])
            ->getActiveSheet()->toArray(null, false, false);
    } catch (\Exception) {
        respond(['status' => 'error', 'message' => 'Could not read file.'], 422);
        $rows = []; // unreachable — respond() exits; satisfies static analysis
    }

    if ($err = validateSheetHeaders($rows[0] ?? [], ['Start Date', 'End Date'])) {
        respond(['status' => 'error', 'message' => $err], 422);
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