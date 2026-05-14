<?php

declare(strict_types=1);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require '../vendor/autoload.php';
require '../db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? '';

/* =========================================================
   DOWNLOAD TEMPLATE
========================================================= */
if ($action === 'download_schedule_template') {
    ob_end_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'A' => 'Employee ID',
        'B' => 'Employee Name',
        'C' => 'Start Date',
        'D' => 'End Date',
        'E' => 'Time',
    ];

    $notes = [
        'A' => 'The employee ID number (e.g. 1).',
        'B' => 'Optional — for reference only.',
        'C' => 'Format: YYYY-MM-DD (e.g. 2026-05-01).',
        'D' => 'Format: YYYY-MM-DD. Same as Start Date for a single day.',
        'E' => 'Format: HH:MM-HH:MM (e.g. 08:00-17:00).',
    ];

    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '1', $label);
    }

    // Header row style
    $sheet->getStyle('A1:E1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '97BE41']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '6A9E2B']]],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    // Column notes
    foreach ($notes as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('200pt');
        $comment->setHeight('50pt');
    }

    // Sample first row
    $sheet->setCellValue('A2', '1');
    $sheet->setCellValue('B2', 'John Doe');
    $sheet->setCellValue('C2', '2026-05-01');
    $sheet->setCellValue('D2', '2026-05-01');
    $sheet->setCellValue('E2', '08:00-17:00');

    // Sample second row
    $sheet->setCellValue('A3', '2');
    $sheet->setCellValue('B3', 'Jane Doe');
    $sheet->setCellValue('C3', '2026-05-02');
    $sheet->setCellValue('D3', '2026-05-02');
    $sheet->setCellValue('E3', '');

    // Sample row styles
    $sheet->getStyle('A2:E2')->applyFromArray([
        'fill'    => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F0F7E6']],
        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']]],
    ]);
    $sheet->getStyle('A3:E3')->applyFromArray([
        'fill'    => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF8E1']],
        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']]],
    ]);

    $sheet->freezePane('A2');

    foreach (array_keys($headers) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="schedule_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
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

        $file        = $_FILES['schedule_file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        $rows        = $spreadsheet->getActiveSheet()->toArray();

        array_shift($rows); // remove header

        $pdo->beginTransaction();

        $inserted   = 0;
        $errors     = [];

        // Validate employee IDs in one query — avoids per-row DB hits
        $allEmpIds  = array_filter(array_unique(array_column($rows, 0)));
        $validIds   = [];

        if (!empty($allEmpIds)) {
            $placeholders = implode(',', array_fill(0, count($allEmpIds), '?'));
            $empStmt      = $pdo->prepare("SELECT id FROM employees WHERE id IN ($placeholders)");
            $empStmt->execute(array_values($allEmpIds));
            $validIds     = array_column($empStmt->fetchAll(PDO::FETCH_ASSOC), 'id', 'id');
        }

        $upsertStmt = $pdo->prepare("
            INSERT INTO schedules
                (employee_id, schedule_date, scheduled_start, scheduled_end)
            VALUES
                (:employee_id, :schedule_date, :start, :end)
            ON DUPLICATE KEY UPDATE
                scheduled_start = VALUES(scheduled_start),
                scheduled_end   = VALUES(scheduled_end)
        ");

        foreach ($rows as $i => $row) {

            $rowNum = $i + 2;

            // Skip fully empty rows
            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) {
                continue;
            }

            $employeeId = trim((string)($row[0] ?? ''));
            $startDate  = trim((string)($row[2] ?? ''));
            $endDate    = trim((string)($row[3] ?? ''));
            $time       = trim((string)($row[4] ?? ''));

            if ($employeeId === '' || $startDate === '' || $endDate === '') {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing required fields'];
                continue;
            }

            if (!isset($validIds[$employeeId])) {
                $errors[] = ['row' => $rowNum, 'message' => "Employee ID $employeeId not found"];
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
                    ':employee_id'   => $employeeId,
                    ':schedule_date' => $date,
                    ':start'         => $startDT,
                    ':end'           => $endDT,
                ]);

                $inserted++;
                $current = strtotime('+1 day', $current);
            }
        }

        $pdo->commit();

        respond([
            'status'   => 'success',
            'inserted' => $inserted,
            'errors'   => $errors,
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) $pdo->rollBack();

        respond([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

/* =========================================================
   DOWNLOAD LEAVES TEMPLATE
========================================================= */
if ($action === 'download_leave_template') {
    ob_end_clean();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $headers = [
        'A' => 'Employee ID',
        'B' => 'Employee Name',
        'C' => 'Buffer Leave',
        'D' => 'Vacation Leave',
        'E' => 'Sick Leave',
        'F' => 'Paternity Leave',
        'G' => 'Maternity Leave',
        'H' => 'Solo Parent Leave',
        'I' => 'Birthday Leave',
    ];

    $notes = [
        'A' => 'The employee ID number (e.g. 1).',
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

    $sheet->getStyle('A1:I1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '97BE41']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '6A9E2B']]],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    foreach ($notes as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('200pt');
        $comment->setHeight('50pt');
    }

    // Sample rows
    $sheet->fromArray(['1', 'John Doe',  0,  5, 4,  7, 90, 1, 1], null, 'A2');
    $sheet->fromArray(['2', 'Jane Doe',  0, 10, 4,  0, 90, 1, 1], null, 'A3');

    $sheet->getStyle('A2:I2')->applyFromArray([
        'fill'    => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F0F7E6']],
        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']]],
    ]);
    $sheet->getStyle('A3:I3')->applyFromArray([
        'fill'    => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF8E1']],
        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'CCCCCC']]],
    ]);

    $sheet->freezePane('A2');

    foreach (array_keys($headers) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="leave_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
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

        $file        = $_FILES['schedule_file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        $rows        = $spreadsheet->getActiveSheet()->toArray();

        array_shift($rows); // remove header

        // Batch validate employee IDs
        $allEmpIds = array_filter(array_unique(array_column($rows, 0)));
        $validIds  = [];

        if (!empty($allEmpIds)) {
            $placeholders = implode(',', array_fill(0, count($allEmpIds), '?'));
            $empStmt      = $pdo->prepare("SELECT id FROM employees WHERE id IN ($placeholders)");
            $empStmt->execute(array_values($allEmpIds));
            $validIds     = array_column($empStmt->fetchAll(PDO::FETCH_ASSOC), 'id', 'id');
        }

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

        foreach ($rows as $i => $row) {

            $rowNum = $i + 2;

            // Skip empty rows
            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) {
                continue;
            }

            $employeeId = trim((string)($row[0] ?? ''));

            if ($employeeId === '') {
                $errors[] = ['row' => $rowNum, 'message' => 'Missing employee ID'];
                continue;
            }

            if (!isset($validIds[$employeeId])) {
                $errors[] = ['row' => $rowNum, 'message' => "Employee ID $employeeId not found"];
                continue;
            }

            // Parse leave values — fall back to DB defaults if blank
            $toInt = fn($v, $default) => is_numeric(trim((string)$v))
                ? (int) trim((string)$v)
                : $default;

            $upsertStmt->execute([
                ':employee_id' => $employeeId,
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

        respond([
            'status'   => 'success',
            'inserted' => $inserted,
            'errors'   => $errors,
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) $pdo->rollBack();

        respond([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

respond(['status' => 'error', 'message' => 'Invalid action'], 400);
