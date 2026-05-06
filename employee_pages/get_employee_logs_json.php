<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        l.id               AS log_id,
        l.log_time,
        l.log_type,
        l.is_within_office,
        l.latitude,
        l.longitude,
        l.accuracy,
        l.distance_meters,
        a.id               AS attendance_id
    FROM logs l
    LEFT JOIN attendances a
        ON  a.employee_id = l.employee_id
        AND DATE(l.log_time) = a.work_date
    WHERE l.employee_id = ?
    ORDER BY l.log_time DESC
    LIMIT 100
");
$stmt->execute([$employeeId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
