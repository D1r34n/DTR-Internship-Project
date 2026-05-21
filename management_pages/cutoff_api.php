<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$pdo->exec("CREATE TABLE IF NOT EXISTS `cutoffs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `start_date` date NOT NULL,
    `end_date`   date NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, start_date, end_date FROM cutoffs ORDER BY start_date DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} elseif ($method === 'POST') {
    $data  = json_decode(file_get_contents('php://input'), true) ?? [];
    $start = trim($data['start_date'] ?? '');
    $end   = trim($data['end_date']   ?? '');

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
    $end   = trim($data['end_date']   ?? '');

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
