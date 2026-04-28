<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = intval($_GET['employee_id'] ?? 0);
$logType    = $_GET['log_type']  ?? '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to']   ?? '';

if (!$employeeId) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

$conditions = ['employee_id = ?'];
$params     = [$employeeId];

if ($logType && in_array($logType, ['IN', 'OUT', 'BREAK_IN', 'BREAK_OUT'])) {
    $conditions[] = 'log_type = ?';
    $params[]     = $logType;
}

if ($dateFrom) {
    $conditions[] = 'DATE(log_time) >= ?';
    $params[]     = $dateFrom;
}

if ($dateTo) {
    $conditions[] = 'DATE(log_time) <= ?';
    $params[]     = $dateTo;
}

$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare("
    SELECT id, log_type, log_time, is_within_office, distance_meters, accuracy, latitude, longitude
    FROM logs
    WHERE $where
    ORDER BY log_time DESC
    LIMIT 500
");
$stmt->execute($params);

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
