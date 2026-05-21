<?php
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['must_change_password'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['change_password_shown'])) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $_SESSION['change_password_shown'] = true;
}

require_once '../db.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$newPassword) {
        $error = "New password is required.";
    } elseif ($newPassword === 'HSN.123') {
        $error = "You cannot use the default password. Please choose a different one.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?")
            ->execute([$newPassword, $_SESSION['user_id']]);

        unset($_SESSION['must_change_password'], $_SESSION['change_password_shown']);

        $role = $_SESSION['user_role'] ?? 'employee';
        header("Location: ../regular_pages/dashboard_page.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — HSN DTR System</title>

    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="login.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container">
        <div class="shine"></div>

        <img src="../assets/images/hsn_logo_white.png" alt="HSN Logo" class="logo">

        <h2 class="page-header">Change Password</h2>
        <p class="text-secondary" style="font-size:0.8rem;margin-bottom:1.25rem;opacity:0.7;">
            Your account is using the default password.<br>Please set a new one before continuing.
        </p>

        <form action="change_password.php" method="POST">

            <div class="field-group">
                <label class="text-secondary">New Password</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        name="new_password"
                        id="new-password-input"
                        placeholder="Enter new password"
                        class="<?= $error ? 'input-error' : '' ?>"
                        autocomplete="new-password"
                    >
                    <button type="button" class="toggle-password" id="toggle-new" aria-label="Toggle visibility">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>
                <?php if ($error): ?>
                    <span class="field-error text-meta">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="field-group">
                <label class="text-secondary">Confirm Password</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm-password-input"
                        placeholder="Confirm new password"
                        autocomplete="new-password"
                    >
                    <button type="button" class="toggle-password" id="toggle-confirm" aria-label="Toggle visibility">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-signin">Save Password</button>

        </form>

        <div class="no-account-group">
            <a href="logout.php" class="no-id text-meta">Log out instead</a>
        </div>
    </div>

    <div class="copyright-bar">
        <p class="copyright">
            &copy; 2026 &nbsp;|&nbsp; Designed by Edrian R. Evangelista &amp;<br>
            Earl David A. Jordan
        </p>
    </div>

    <script>
        const container = document.querySelector('.container');
        const shine     = document.querySelector('.shine');

        container.addEventListener('mousemove', e => {
            const rect = container.getBoundingClientRect();
            container.style.setProperty('--x', `${e.clientX - rect.left}px`);
            container.style.setProperty('--y', `${e.clientY - rect.top}px`);
        });
        container.addEventListener('mouseleave', () => {
            container.style.setProperty('--x', '50%');
            container.style.setProperty('--y', '50%');
        });

        function initToggle(btnId, inputId) {
            document.getElementById(btnId).addEventListener('click', () => {
                const input = document.getElementById(inputId);
                const hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                document.getElementById(btnId).innerHTML = hidden
                    ? '<i class="bi bi-eye-slash-fill"></i>'
                    : '<i class="bi bi-eye-fill"></i>';
            });
        }

        initToggle('toggle-new',     'new-password-input');
        initToggle('toggle-confirm', 'confirm-password-input');
    </script>
</body>
</html>
