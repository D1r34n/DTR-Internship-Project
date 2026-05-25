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
        l.id AS log_id,
        l.log_time,
        l.log_type,
        a.id AS attendance_id,
        CASE WHEN l.edit_status = 'pending'  THEN 1 ELSE 0 END AS has_pending,
        CASE WHEN l.edit_status = 'approved' THEN 1 ELSE 0 END AS has_approved

    FROM logs l
    LEFT JOIN attendances a
        ON  a.employee_id = l.employee_id
        AND DATE(l.log_time) = a.work_date

    WHERE l.employee_id = ?
        AND DATE(l.log_time) = CURDATE()
        AND l.log_type IN ('IN', 'OUT')

    ORDER BY l.log_time ASC
");
$stmt->execute([$employeeId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
