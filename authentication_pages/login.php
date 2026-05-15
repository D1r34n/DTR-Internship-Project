<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../db.php';

$page_title = "HSN DTR System";

$error_email    = $_SESSION['error_email']    ?? "";
$error_password = $_SESSION['error_password'] ?? "";
$old_email      = $_SESSION['old_email']      ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $_SESSION['old_email'] = $email;

    if (!$email && !$password) {
        $_SESSION['error_email']    = "Email is required.";
        $_SESSION['error_password'] = "Password is required.";
        header("Location: login.php");
        exit();
    }

    if (!$email) {
        $_SESSION['error_email'] = "Email is required.";
        header("Location: login.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_email'] = "Please enter a valid email address.";
        header("Location: login.php");
        exit();
    }

    if (!$password) {
        $_SESSION['error_password'] = "Password is required.";
        header("Location: login.php");
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS name, e.profile_image, e.email, e.password, r.role_key AS role
        FROM employees e
        LEFT JOIN roles r ON r.id = e.role_id
        WHERE e.email = ?
    ");
    $stmt->execute([$email]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error_email'] = "Invalid email or password.";
        header("Location: login.php");
        exit();
    }

    if ($password === $employee['password']) {

        session_regenerate_id(true);

        $_SESSION['user_id']    = $employee['id'];
        $_SESSION['profile_image'] = $employee['profile_image'];
        $_SESSION['user_email'] = $employee['email'];
        $_SESSION['user_name']  = $employee['name'];
        $_SESSION['user_role']  = $employee['role'];

        unset($_SESSION['error_email'], $_SESSION['error_password'], $_SESSION['old_email']);

        if ($employee['password'] === 'HSN.123') {
            $_SESSION['must_change_password'] = true;
            header("Location: change_password.php");
            exit();
        }

        if ($employee['role'] === 'admin') {
            header("Location: ../admin_pages/dashboard_page.php");
        } else {
            header("Location: ../employee_pages/employee_dashboard.php");
        }
        exit();

    } else {
        $_SESSION['error_password'] = "Incorrect password.";
        header("Location: login.php");
        exit();
    }
}

unset($_SESSION['error_email'], $_SESSION['error_password'], $_SESSION['old_email']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/root.css">
    <link rel="stylesheet" href="../assets/css/typography.css">
    <link rel="stylesheet" href="login.css">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container">
        <div class="shine"></div>

        <img src="../assets/images/hsn_logo_white.png" alt="HSN Logo" class="logo">

        <h2 class="page-header">Sign In</h2>

        <form action="login.php" method="POST">

            <div class="field-group">
                <label class="text-secondary">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email-input"
                    placeholder="Enter your email"
                    autocomplete="username"
                    class="<?= $error_email ? 'input-error' : '' ?>"
                    value="<?= htmlspecialchars($old_email) ?>"
                >
                <?php if ($error_email): ?>
                    <span class="field-error text-meta">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error_email) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="field-group password-group">
                <label class="text-secondary">Password</label>

                <div class="password-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="password-input"
                        placeholder="Enter your password"
                        class="<?= $error_password ? 'input-error' : '' ?>"
                    >
                    <button type="button" class="toggle-password" id="toggle-password" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>

                <?php if ($error_password): ?>
                    <span class="field-error text-meta">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error_password) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="remember-row">
                <label class="remember-me text-meta">
                    <input type="checkbox"> Remember me
                </label>
                <a href="https://www.hsnservice.com/" target="_blank" class="forgot-password text-meta">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="btn-signin">Sign In</button>

        </form>

        <div class="no-account-group">
            <span class="text-meta">Don't have an HSN ID?</span>
            <a href="mailto:Service.Hsnc@hsnservice.com" target="_blank" class="no-id text-meta">
                Contact your HR admin
            </a>
        </div>
    </div>

    <div class="copyright-bar">
        <p class="copyright">
            &copy; 2026 &nbsp;|&nbsp; Designed by Edrian R. Evangelista &amp;<br>
            Earl David A. Jordan
        </p>
    </div>

    <script>
        const container     = document.querySelector('.container');
        const shine         = document.querySelector('.shine');
        const toggleBtn     = document.getElementById('toggle-password');
        const passwordInput = document.getElementById('password-input');

        // Shine mouse tracking
        container.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            container.style.setProperty('--x', `${e.clientX - rect.left}px`);
            container.style.setProperty('--y', `${e.clientY - rect.top}px`);
        });

        container.addEventListener('mouseleave', () => {
            container.style.setProperty('--x', '50%');
            container.style.setProperty('--y', '50%');
        });

        // Password visibility toggle
        toggleBtn.addEventListener('click', () => {
            const isHidden      = passwordInput.type === 'password';
            passwordInput.type  = isHidden ? 'text' : 'password';
            toggleBtn.innerHTML = isHidden
                ? '<i class="bi bi-eye-slash-fill"></i>'
                : '<i class="bi bi-eye-fill"></i>';
        });
    </script>
</body>
</html>