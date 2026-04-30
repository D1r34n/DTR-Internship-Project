
<?php
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'workforce') {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$myId       = $_SESSION['user_id'];
$employeeId = intval($_GET['employee_id'] ?? 0);
$logType    = $_GET['log_type']  ?? '';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to']   ?? '';

if (!$employeeId) { echo json_encode([]); exit(); }

// Verify employee is in same department as the logged-in workforce user
$myDeptRow = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$myDeptRow->execute([$myId]);
$myDept = $myDeptRow->fetchColumn();

$empDeptRow = $pdo->prepare("SELECT department_id FROM employees WHERE id = ?");
$empDeptRow->execute([$employeeId]);
$empDept = $empDeptRow->fetchColumn();

if (!$myDept || $myDept !== $empDept) {
    http_response_code(403);
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

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
