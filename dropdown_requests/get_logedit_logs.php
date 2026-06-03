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
        CASE WHEN ler.status = 'pending'  THEN 1 ELSE 0 END AS has_pending,
        CASE WHEN ler.status = 'approved' THEN 1 ELSE 0 END AS has_approved

    FROM logs l
    LEFT JOIN attendances a
        ON  a.employee_id = l.employee_id
        AND a.schedule_id = l.schedule_id
    LEFT JOIN log_edit_requests ler
        ON  ler.log_id = l.id
        AND ler.id = (SELECT MAX(id) FROM log_edit_requests WHERE log_id = l.id)

    WHERE l.employee_id = ?
        AND l.log_type IN ('IN', 'OUT')
        AND l.schedule_id = (
            SELECT schedule_id
            FROM logs
            WHERE employee_id = ?
              AND log_type = 'IN'
              AND schedule_id IS NOT NULL
            ORDER BY log_time DESC
            LIMIT 1
        )

    ORDER BY l.log_time ASC
");
$stmt->execute([$employeeId, $employeeId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
