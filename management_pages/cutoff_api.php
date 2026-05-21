<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
date_default_timezone_set('Asia/Manila');

$pdo->exec("CREATE TABLE IF NOT EXISTS `cutoffs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `start_date` date NOT NULL,
    `end_date`   date NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Prevent Overlapping
function hasCutoffOverlap(PDO $pdo, string $start, string $end, ?int $ignoreId = null): bool {
    $sql = "
        SELECT COUNT(*) 
        FROM cutoffs
        WHERE start_date <= :end
          AND end_date >= :start
    ";

    if ($ignoreId) {
        $sql .= " AND id != :id";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);

    if ($ignoreId) {
        $stmt->bindValue(':id', $ignoreId, PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}

// ── TEMPLATE DOWNLOAD ────────────────────────────────────────
if ($action === 'download_template') {
    require_once '../vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Start Date');
    $sheet->setCellValue('B1', 'End Date');

    $sheet->getStyle('A1:B1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '97BE41']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '6A9E2B']]],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);

    foreach (['A' => 'Format: MMM D, YYYY (e.g. May 1, 2026).', 'B' => 'Format: MMM D, YYYY. Must be on or after Start Date.'] as $col => $note) {
        $comment = $sheet->getComment($col . '1');
        $comment->getText()->createTextRun($note);
        $comment->setWidth('200pt')->setHeight('50pt');
    }

    $sheet->setCellValue('A2', 'May 1, 2026');
    $sheet->setCellValue('B2', 'May 15, 2026');
    $sheet->setCellValue('A3', 'May 16, 2026');
    $sheet->setCellValue('B3', 'May 31, 2026');

    foreach (['A', 'B'] as $col) {
        $sheet->getColumnDimension($col)->setWidth(16);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="cutoff_template.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

// ── BULK IMPORT ───────────────────────────────────────────────
if ($action === 'import' && $method === 'POST') {
    header('Content-Type: application/json');
    require_once '../vendor/autoload.php';

    if (empty($_FILES['cutoff_file']['tmp_name'])) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded.']);
        exit;
    }

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['cutoff_file']['tmp_name']);
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, false, false);
    } catch (\Exception $e) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Could not read file.']);
        exit;
    }

    function parseExcelDate(mixed $value): ?string {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('Y-m-d');
            } catch (\Exception) { return null; }
        }
        $v = trim((string)$value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        try { return (new \DateTime($v))->format('Y-m-d'); } catch (\Exception) { return null; }
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
            $errors[] = [
                'row' => $rowNum,
                'message' => 'Overlaps with existing cut-off'
            ];
            continue;
        }

        try {
            $stmt->execute([$start, $end]);
            $inserted++;
        } catch (\Exception $e) {
            $errors[] = ['row' => $rowNum, 'message' => 'Database error'];
        }
    }

    echo json_encode(['status' => 'success', 'inserted' => $inserted, 'errors' => $errors]);
    exit;
}

header('Content-Type: application/json');

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, start_date, end_date FROM cutoffs ORDER BY start_date DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} elseif ($method === 'POST') {
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $start = trim($data['start_date'] ?? '');
    $end   = trim($data['end_date'] ?? '');

    if (!$start || !$end) {
        http_response_code(422);
        echo json_encode(['error' => 'start_date and end_date are required']);
        exit();
    }

    if ($start > $end) {
        http_response_code(422);
        echo json_encode(['error' => 'start_date must not be after end_date']);
        exit();
    }

    // 🔥 OVERLAP CHECK
    if (hasCutoffOverlap($pdo, $start, $end)) {
        http_response_code(409);
        echo json_encode(['error' => 'Cut-off overlaps with an existing period']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO cutoffs (start_date, end_date) VALUES (?, ?)");
    $stmt->execute([$start, $end]);

    echo json_encode([
        'id'         => (int)$pdo->lastInsertId(),
        'start_date' => $start,
        'end_date'   => $end,
    ]);

} elseif ($method === 'PUT') {
    $id    = (int)($_GET['id'] ?? 0);
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $start = trim($data['start_date'] ?? '');
    $end   = trim($data['end_date'] ?? '');

    if (!$id || !$start || !$end) {
        http_response_code(422);
        echo json_encode(['error' => 'id, start_date and end_date are required']);
        exit();
    }

    if ($start > $end) {
        http_response_code(422);
        echo json_encode(['error' => 'start_date must not be after end_date']);
        exit();
    }

    // 🔥 OVERLAP CHECK (exclude self)
    if (hasCutoffOverlap($pdo, $start, $end, $id)) {
        http_response_code(409);
        echo json_encode(['error' => 'Updated range overlaps with another cut-off period']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE cutoffs SET start_date = ?, end_date = ? WHERE id = ?");
    $stmt->execute([$start, $end, $id]);

    echo json_encode([
        'id'         => $id,
        'start_date' => $start,
        'end_date'   => $end,
    ]);

} elseif ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(422);
        echo json_encode(['error' => 'id is required']);
        exit();
    }
    $stmt = $pdo->prepare("DELETE FROM cutoffs WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
