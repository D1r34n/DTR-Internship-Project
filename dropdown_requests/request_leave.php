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

$validTypes = ['sick leave', 'vacation leave', 'birthday leave', 'solo parent leave'];
if (!in_array(strtolower($leaveType), $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid leave type.']);
    exit();
}

$today         = date('Y-m-d');
$datesArray    = json_decode($selectedDates, true);

if (!is_array($datesArray) || empty($datesArray)) {
    echo json_encode(['success' => false, 'message' => 'No dates selected.']);
    exit();
}

$days = count($datesArray);

// ---- PER TYPE RULES ----
if (strtolower($leaveType) === 'sick leave') {
    foreach ($datesArray as $d) {
        if ($d >= $today) {
            echo json_encode(['success' => false, 'message' => 'Sick leave can only be filed for past dates (before today).']);
            exit();
        }
    }
    if ($days > 4) {
        echo json_encode(['success' => false, 'message' => 'Sick leave is limited to 4 days maximum.']);
        exit();
    }
}

if (strtolower($leaveType) === 'vacation leave') {
    foreach ($datesArray as $d) {
        if ($d <= $today) {
            echo json_encode(['success' => false, 'message' => 'Vacation leave can only be filed for future dates.']);
            exit();
        }
    }
}

if (strtolower($leaveType) === 'birthday leave') {
    if ($days > 1) {
        echo json_encode(['success' => false, 'message' => 'Birthday leave is limited to 1 day only.']);
        exit();
    }
}

if (strtolower($leaveType) === 'solo parent leave') {
    if ($days > 2) {
        echo json_encode(['success' => false, 'message' => 'Solo parent leave is limited to 2 days maximum.']);
        exit();
    }
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
        INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, selected_dates, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$employeeId, $leaveType, $startDate, $endDate, $selectedDates, $reason, $initialStatus]);

    $msg = $autoApprove ? 'Leave request approved.' : 'Leave request submitted successfully!';
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
