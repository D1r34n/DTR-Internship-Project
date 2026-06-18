<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['expired' => true]);
    exit();
}

$_SESSION['last_activity'] = time();
echo json_encode(['ok' => true, 'remaining' => SESSION_TIMEOUT ?? 1800]);
