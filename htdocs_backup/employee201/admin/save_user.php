<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin','admin'])) {
    die("Unauthorized access");
}

$username = trim($_POST['username']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$role = $_POST['role'];

if ($password !== $confirm_password) {
    die("Passwords do not match.");
}

// Role restrictions
if ($_SESSION['role'] === 'admin' && $role === 'superadmin') {
    die("Admins cannot create Superadmin accounts.");
}

$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO users (username, password, role, date_added) VALUES (?, ?, ?, NOW())");
$stmt->execute([$username, $hashedPassword, $role]);

header("Location: manage_users.php");
exit;
