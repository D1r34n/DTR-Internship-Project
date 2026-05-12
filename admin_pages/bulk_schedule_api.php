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
if ($action === 'download_template') {

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
        'A' => 'The employee ID number (e.g. 1001).',
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
    $sheet->setCellValue('A2', '1001');
    $sheet->setCellValue('B2', 'John Doe');
    $sheet->setCellValue('C2', '2026-05-01');
    $sheet->setCellValue('D2', '2026-05-01');
    $sheet->setCellValue('E2', '08:00-17:00');

    // Sample second row
    $sheet->setCellValue('A3', '1002');
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
   IMPORT (UPSERT / OVERRIDE VERSION)
========================================================= */
if ($action === 'import') {

    try {

        if (!isset($_FILES['schedule_file'])) {
            respond(['status' => 'error', 'message' => 'No file uploaded'], 400);
        }

        $file = $_FILES['schedule_file']['tmp_name'];

        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        array_shift($rows); // remove header

        $pdo->beginTransaction();

        $inserted = 0;
        $errors = [];

        // ✅ UPSERT STATEMENT (INSERT OR UPDATE)
        $upsertStmt = $pdo->prepare("
            INSERT INTO schedules
            (employee_id, schedule_date, scheduled_start, scheduled_end)
            VALUES (:employee_id, :schedule_date, :start, :end)
            ON DUPLICATE KEY UPDATE
                scheduled_start = VALUES(scheduled_start),
                scheduled_end   = VALUES(scheduled_end)
        ");

        foreach ($rows as $i => $row) {

            $rowNum = $i + 2;

            $employeeId = trim((string)($row[0] ?? ''));
            $startDate  = trim((string)($row[2] ?? ''));
            $endDate    = trim((string)($row[3] ?? ''));
            $time       = trim((string)($row[4] ?? ''));

            if ($employeeId === '' || $startDate === '' || $endDate === '') {
                $errors[] = "Row $rowNum: missing required fields";
                continue;
            }

            $current = strtotime($startDate);
            $end     = strtotime($endDate);

            if (!$current || !$end) {
                $errors[] = "Row $rowNum: invalid date";
                continue;
            }

            $startTime = null;
            $endTime   = null;

            if (empty($time) || !str_contains($time, '-')) {
                $errors[] = "Row $rowNum: invalid or missing time format";
                continue;
            }

            [$startTime, $endTime] = array_map('trim', explode('-', $time));

            while ($current <= $end) {

                $date = date('Y-m-d', $current);

                $startDT = $date . ' ' . $startTime . ':00';
                $endDT   = $date . ' ' . $endTime . ':00';

                $upsertStmt->execute([
                    ':employee_id'    => $employeeId,
                    ':schedule_date'  => $date,
                    ':start'          => $startDT,
                    ':end'            => $endDT,
                ]);

                $inserted++;
                $current = strtotime("+1 day", $current);
            }
        }

        $pdo->commit();

        respond([
            'status'   => 'success',
            'inserted' => $inserted,
            'errors'   => $errors
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) $pdo->rollBack();

        respond([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

respond(['status' => 'error', 'message' => 'Invalid action'], 400);