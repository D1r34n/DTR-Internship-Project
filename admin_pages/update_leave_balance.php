<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    exit();
}

require_once '../db.php';
header('Content-Type: application/json');

$employeeId = intval($_POST['employee_id'] ?? 0);
$leaveType  = $_POST['leave_type'] ?? '';
$value      = intval($_POST['value'] ?? 0);

$allowed = [
    'vacation_leave', 'sick_leave', 'birthday_leave',
    'paternity_leave', 'maternity_leave', 'solo_parent_leave', 'buffer_leave',
];

if (!$employeeId || !in_array($leaveType, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit();
}

if ($value < 0) $value = 0;

$stmt = $pdo->prepare("
    INSERT INTO employee_leave_balances (employee_id, `$leaveType`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `$leaveType` = VALUES(`$leaveType`)
");
$stmt->execute([$employeeId, $value]);

echo json_encode(['ok' => true, 'value' => $value]);
