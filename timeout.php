<?php
session_start();
require_once 'db.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user_email'];
$now = date('H:i:s');
$today = date('Y-m-d');

// Late if after 8:30 AM
$status = ($now > '08:30:00') ? 'Late' : 'Present';

$stmt = $pdo->prepare("INSERT INTO attendance (employee_email, date, time_in, status) VALUES (?, ?, ?, ?)");
$stmt->execute([$email, $today, $now, $status]);

$_SESSION['timedIn'] = true;
$_SESSION['time_in'] = $now;
$_SESSION['attendance_id'] = $pdo->lastInsertId();

echo "timed_in";
?>