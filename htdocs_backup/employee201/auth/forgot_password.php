<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);

    // Check in admins table
    $stmt = $conn->prepare("SELECT id, username, email FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    // Check in employee_users table if not admin
    if (!$admin) {
        $stmt = $conn->prepare("SELECT id, username, email FROM employee_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
    }

    $user = $admin ?? $employee;

    if ($user) {
        // Generate reset token
        $token = bin2hex(random_bytes(16));
        $expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        // Ensure table exists
        $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255),
            token VARCHAR(64),
            expires_at DATETIME
        )");

        $stmt = $conn->prepare("INSERT INTO password_resets (username, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $token, $expires);
        $stmt->execute();

        $resetLink = "http://localhost/employee201/auth/reset_password.php?token=$token";

        // ✅ Send via email
        require_once __DIR__ . '/../includes/mail_config.php';
        $sent = sendResetEmail($user['email'], $user['username'], $resetLink);

        if ($sent) {
            $message = "<div class='success'>✅ A password reset link has been sent to your email.</div>";
        } else {
            $message = "<div class='warning'>⚠️ Unable to send reset email. Please contact your administrator.</div>";
        }
    } else {
        $message = "<div class='error'>❌ Username not found!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body {
        font-family: "Segoe UI", Tahoma, sans-serif;
        background: radial-gradient(circle at top left, #2d2f45, #1e1e2d);
        color: #f9fafb;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100vh;
        margin: 0;
    }

    .container {
        background: #ffffff10;
        backdrop-filter: blur(10px);
        border: 1px solid #ffffff20;
        border-radius: 16px;
        padding: 40px 30px;
        width: 360px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        text-align: center;
        transition: all 0.3s ease;
    }

    .container:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
    }

    h2 {
        margin-bottom: 10px;
        font-size: 1.6rem;
        color: #ffffff;
    }

    p {
        font-size: 0.9rem;
        color: #d1d5db;
        margin-bottom: 20px;
    }

    input[type=text] {
        width: 90%;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #4b5563;
        background: #1f2937;
        color: white;
        margin-bottom: 15px;
        transition: 0.3s;
    }

    input[type=text]:focus {
        border-color: #6366f1;
        box-shadow: 0 0 8px #6366f1;
        outline: none;
    }

    input[type=submit] {
        background: linear-gradient(90deg, #4f46e5, #6366f1);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 8px;
        width: 100%;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s, transform 0.2s;
    }

    input[type=submit]:hover {
        background: linear-gradient(90deg, #4338ca, #4f46e5);
        transform: translateY(-2px);
    }

    a {
        display: inline-block;
        color: #a5b4fc;
        text-decoration: none;
        margin-top: 15px;
        font-size: 0.9rem;
        transition: color 0.2s;
    }

    a:hover {
        color: #c7d2fe;
    }

    .message {
        margin-top: 15px;
        font-size: 0.9rem;
        text-align: left;
    }

    .success {
        color: #10b981;
        background: #064e3b50;
        border-left: 4px solid #10b981;
        padding: 8px 12px;
        border-radius: 6px;
    }

    .warning {
        color: #f59e0b;
        background: #78350f50;
        border-left: 4px solid #f59e0b;
        padding: 8px 12px;
        border-radius: 6px;
    }

    .error {
        color: #ef4444;
        background: #7f1d1d50;
        border-left: 4px solid #ef4444;
        padding: 8px 12px;
        border-radius: 6px;
    }

    @media (max-width: 480px) {
        .container {
            width: 90%;
            padding: 25px 20px;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>
        <p>Enter your username to receive a reset link.</p>
        <form method="POST" autocomplete="off">
            <input type="text" name="username" placeholder="Enter your username" required>
            <input type="submit" value="Request Reset">
        </form>
        <div class="message"><?= $message ?></div>
        <a href="login.php">← Back to Login</a>
    </div>
</body>
</html>
