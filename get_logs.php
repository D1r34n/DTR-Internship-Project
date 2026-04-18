<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$employeeId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT log_time, log_type
    FROM logs
    WHERE employee_id = ?
    ORDER BY log_time DESC
");

$stmt->execute([$employeeId]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($records);