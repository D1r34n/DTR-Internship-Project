<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId = $_SESSION['user_id'];
$today      = date('Y-m-d');
$leaveType  = $_GET['leave_type'] ?? '';

// ---- SET DATE DIRECTION BASED ON LEAVE TYPE ----
if ($leaveType === 'sick leave') {
    // Past scheduled work days only
    $whereDate = "AND schedule_date < ?";
    $dateParam = $today;
} elseif ($leaveType === 'vacation leave') {
    // Future scheduled work days only
    $whereDate = "AND schedule_date > ?";
    $dateParam = $today;
} else {
    // Birthday leave / Solo parent leave — any date
    $whereDate = "AND schedule_date != ?";
    $dateParam = '0000-00-00'; // dummy so query still works
}

$stmt = $pdo->prepare("
    SELECT schedule_date
    FROM schedules
    WHERE employee_id = ?
    AND is_rest_day = 0
    {$whereDate}
    ORDER BY schedule_date ASC
");

$stmt->execute([$employeeId, $dateParam]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$scheduledDates = array_column($rows, 'schedule_date');

// Collect all dates already covered by approved or pending leave requests
$leaveStmt = $pdo->prepare("
    SELECT selected_dates
    FROM leave_requests
    WHERE employee_id = ?
    AND status IN ('approved', 'pending')
");
$leaveStmt->execute([$employeeId]);

$leaveDates = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $decoded = json_decode($row['selected_dates'], true);
    if (is_array($decoded)) {
        $leaveDates = array_merge($leaveDates, $decoded);
    }
}
$leaveDates = array_values(array_unique($leaveDates));

echo json_encode([
    'scheduledDates' => $scheduledDates,
    'leaveDates'     => $leaveDates,
]);