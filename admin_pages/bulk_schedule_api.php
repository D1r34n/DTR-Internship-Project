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
        'employee_id',
        'employee_name',
        'start_date',
        'end_date',
        'time',
        'is_rest_day'
    ];

    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    // sample normal
    $sheet->setCellValue('A2', '1001');
    $sheet->setCellValue('B2', 'John Doe');
    $sheet->setCellValue('C2', '2026-05-01');
    $sheet->setCellValue('D2', '2026-05-01');
    $sheet->setCellValue('E2', '08:00-17:00');
    $sheet->setCellValue('F2', '0');

    // sample rest day
    $sheet->setCellValue('A3', '1002');
    $sheet->setCellValue('B3', 'Jane Doe');
    $sheet->setCellValue('C3', '2026-05-02');
    $sheet->setCellValue('D3', '2026-05-02');
    $sheet->setCellValue('E3', '');
    $sheet->setCellValue('F3', '1');

    foreach (range('A', 'F') as $c) {
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
            (employee_id, schedule_date, scheduled_start, scheduled_end, is_rest_day)
            VALUES (:employee_id, :schedule_date, :start, :end, :rest)
            ON DUPLICATE KEY UPDATE
                scheduled_start = VALUES(scheduled_start),
                scheduled_end   = VALUES(scheduled_end),
                is_rest_day     = VALUES(is_rest_day)
        ");

        foreach ($rows as $i => $row) {

            $rowNum = $i + 2;

            $employeeId = trim((string)($row[0] ?? ''));
            $startDate  = trim((string)($row[2] ?? ''));
            $endDate    = trim((string)($row[3] ?? ''));
            $time       = trim((string)($row[4] ?? ''));
            $isRest     = (int)($row[5] ?? 0);

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

            if (!$isRest) {
                if (!str_contains($time, '-')) {
                    $errors[] = "Row $rowNum: invalid time format";
                    continue;
                }

                [$startTime, $endTime] = array_map('trim', explode('-', $time));
            }

            while ($current <= $end) {

                $date = date('Y-m-d', $current);

                $startDT = $isRest ? null : ($date . ' ' . $startTime . ':00');
                $endDT   = $isRest ? null : ($date . ' ' . $endTime . ':00');

                // ✅ INSERT OR UPDATE (NO DUPLICATE CHECK NEEDED)
                $upsertStmt->execute([
                    ':employee_id'    => $employeeId,
                    ':schedule_date'  => $date,
                    ':start'          => $startDT,
                    ':end'            => $endDT,
                    ':rest'           => $isRest
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