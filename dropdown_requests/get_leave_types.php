<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}
require_once '../db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT name, label, max_days, direction, description_label
    FROM leave_types
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
