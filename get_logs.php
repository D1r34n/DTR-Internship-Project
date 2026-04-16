<?php
require_once 'db.php';

session_start();
$employeeId = $_SESSION['user_id'];

$date = isset($_GET['date']) ? $_GET['date'] : date("Y-m-d");

$stmt = $pdo->prepare("
    SELECT log_time, log_type
    FROM logs
    WHERE employee_id = ?
    AND DATE(log_time) = ?
    ORDER BY log_time DESC
");

$stmt->execute([$employeeId, $date]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>