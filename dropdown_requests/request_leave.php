<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require_once '../db.php';
date_default_timezone_set('Asia/Manila');

$employeeId    = $_SESSION['user_id'];
$autoApprove   = in_array($_SESSION['user_role'], ['superadmin', 'admin', 'manager']);
$initialStatus = $autoApprove ? 'approved' : 'pending';
$leaveType     = trim($_POST['leave_type']     ?? '');
$startDate     = trim($_POST['start_date']     ?? '');
$endDate       = trim($_POST['end_date']       ?? '');
$reason        = trim($_POST['reason']         ?? '');
$selectedDates = trim($_POST['selected_dates'] ?? '');

// ---- BASIC VALIDATION ----
if (!$leaveType || !$startDate || !$endDate || !$reason || !$selectedDates) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

// ---- LOAD LEAVE TYPE RULES FROM DB ----
$typeStmt = $pdo->query("SELECT id, name, label, max_days, direction FROM leave_types WHERE is_active = 1");
$leaveTypeRules = [];
foreach ($typeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $leaveTypeRules[strtolower($row['name'])] = $row;
}

if (!array_key_exists(strtolower($leaveType), $leaveTypeRules)) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave type.']);
    exit();
}

$typeRule = $leaveTypeRules[strtolower($leaveType)];

$today      = date('Y-m-d');
$datesArray = json_decode($selectedDates, true);

if (!is_array($datesArray) || empty($datesArray)) {
    echo json_encode(['success' => false, 'message' => 'No dates selected.']);
    exit();
}

$days = count($datesArray);

// ---- PER TYPE RULES (from DB) ----
if ($typeRule['direction'] === 'past') {
    foreach ($datesArray as $d) {
        if ($d >= $today) {
            echo json_encode(['success' => false, 'message' => "{$typeRule['label']} can only be filed for past dates (before today)."]);
            exit();
        }
    }
}

if ($typeRule['direction'] === 'future') {
    foreach ($datesArray as $d) {
        if ($d <= $today) {
            echo json_encode(['success' => false, 'message' => "{$typeRule['label']} can only be filed for future dates."]);
            exit();
        }
    }
}

if ($typeRule['max_days'] < 999 && $days > $typeRule['max_days']) {
    $dayWord = $typeRule['max_days'] === 1 ? 'day' : 'days';
    echo json_encode(['success' => false, 'message' => "{$typeRule['label']} is limited to {$typeRule['max_days']} {$dayWord} maximum."]);
    exit();
}

// ---- ANNUAL CAP CHECK (16 days per year; approved + pending both count) ----
$year    = date('Y', strtotime($startDate));
$capStmt = $pdo->prepare("
    SELECT selected_dates
    FROM leave_requests
    WHERE employee_id = ?
    AND status IN ('approved', 'pending')
    AND YEAR(start_date) = ?
");
$capStmt->execute([$employeeId, $year]);
$usedRows = $capStmt->fetchAll(PDO::FETCH_ASSOC);

$usedDays = 0;
foreach ($usedRows as $row) {
    $dates = json_decode($row['selected_dates'], true);
    if (is_array($dates)) {
        $usedDays += count($dates);
    }
}

if ($usedDays >= 16) {
    echo json_encode([
        'success' => false,
        'message' => 'No more remaining leave requests.'
    ]);
    exit();
}

if ($usedDays + $days > 16) {
    $remaining = max(0, 16 - $usedDays);
    echo json_encode([
        'success' => false,
        'message' => "You have only {$remaining} leave day(s) remaining for {$year}. You cannot exceed the 16-day annual limit."
    ]);
    exit();
}

// ---- CHECK FOR DUPLICATE PENDING REQUEST ----
$check = $pdo->prepare("
    SELECT COUNT(*) FROM leave_requests
    WHERE employee_id = ?
    AND status = 'pending'
    AND (
        start_date BETWEEN ? AND ?
        OR end_date BETWEEN ? AND ?
    )
");
$check->execute([$employeeId, $startDate, $endDate, $startDate, $endDate]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending leave request for these dates.']);
    exit();
}

// ---- INSERT ----
try {
    $pdo->prepare("
        INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, selected_dates, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$employeeId, $typeRule['id'], $startDate, $endDate, $selectedDates, $reason, $initialStatus]);

    $msg = $autoApprove ? 'Leave request approved.' : 'Leave request submitted successfully!';
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
