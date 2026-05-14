<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$token = $_GET['token'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password !== $confirm) {
        $message = "<div class='error'>❌ Passwords do not match!</div>";
    } else {
        $stmt = $conn->prepare("SELECT username, expires_at FROM password_resets WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if ($res) {
            $expires_at = strtotime($res['expires_at']);
            if ($expires_at > time()) {
                $username = $res['username'];
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                // Update both tables
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
                $stmt->bind_param("ss", $hashed, $username);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE employee_users SET password = ? WHERE username = ?");
                $stmt->bind_param("ss", $hashed, $username);
                $stmt->execute();

                // Delete token
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();

                $message = "<div class='success'>✅ Password successfully reset! <a href='login.php'>Login now</a></div>";
            } else {
                $message = "<div class='warning'>❌ This reset link has expired.</div>";
            }
        } else {
            $message = "<div class='error'>❌ Invalid reset link.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password</title>
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
        text-align: center;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
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

    input[type=password] {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #4b5563;
        background: #1f2937;
        color: white;
        margin-bottom: 15px;
        transition: 0.3s;
    }

    input[type=password]:focus {
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
        width: 90%;
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

    .success, .warning, .error {
        margin-top: 15px;
        font-size: 0.9rem;
        padding: 10px 12px;
        border-radius: 6px;
        text-align: left;
    }

    .success {
        color: #10b981;
        background: #064e3b50;
        border-left: 4px solid #10b981;
    }

    .warning {
        color: #f59e0b;
        background: #78350f50;
        border-left: 4px solid #f59e0b;
    }

    .error {
        color: #ef4444;
        background: #7f1d1d50;
        border-left: 4px solid #ef4444;
    }

    @media (max-width: 480px) {
        .container {
            width: 90%;
            padding: 25px 20px;
        }
    }

    /* Smooth fade-in animation */
    .container, input, .message {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>

        <?= $message ? "<div class='message'>$message</div>" : '' ?>

        <?php if ($token && empty($message)): ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="password" name="password" placeholder="New Password" required>
            <input type="password" name="confirm" placeholder="Confirm Password" required>
            <input type="submit" value="Reset Password">
        </form>
        <?php endif; ?>

        <a href="login.php">← Back to Login</a>
    </div>
</body>
</html>
