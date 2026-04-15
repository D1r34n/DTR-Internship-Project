<?php
session_start();
require_once 'db.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$employeeId = $_SESSION['user_id'];
$today = date('Y-m-d');

if (!isset($_SESSION['timedIn'])) {
    $_SESSION['timedIn'] = false;
}

if ($_SESSION['timedIn'] === false) {
    // TIME IN — insert login log
    $stmt = $pdo->prepare("INSERT INTO logs (employee_id, log_type) VALUES (?, 'login')");
    $stmt->execute([$employeeId]);
    $_SESSION['timedIn'] = true;
    echo "timed_in";

} else {
    // TIME OUT — insert logout log
    $stmt = $pdo->prepare("INSERT INTO logs (employee_id, log_type) VALUES (?, 'logout')");
    $stmt->execute([$employeeId]);
    $_SESSION['timedIn'] = false;
    echo "timed_out";
}
?>