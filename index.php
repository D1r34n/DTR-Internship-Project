<?php
session_start();
require_once 'db.php';

$page_title = "HSN DRT System";

$error_email    = $_SESSION['error_email']    ?? "";
$error_password = $_SESSION['error_password'] ?? "";
unset($_SESSION['error_email'], $_SESSION['error_password']);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email && !$password) {
        $_SESSION['error_email']    = "Email is required.";
        $_SESSION['error_password'] = "Password is required.";
        header("Location: index.php");
        exit();
    }

    if (!$email) {
        $_SESSION['error_email'] = "Email is required.";
        header("Location: index.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_email'] = "Please enter a valid email address.";
        header("Location: index.php");
        exit();
    }

    if (!$password) {
        $_SESSION['error_password'] = "Password is required.";
        header("Location: index.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM employees WHERE email = ?");
    $stmt->execute([$email]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error_email'] = "Invalid email or password.";
        header("Location: index.php");
        exit();
    }

    // Password Check if hashed
    // if (password_verify($password, $employee['password'])) {
    if ($password === $employee['password']) {

        session_regenerate_id(true);

        $_SESSION['user_email'] = $employee['email'];
        $_SESSION['user_name']  = $employee['name'];
        $_SESSION['user_id']    = $employee['id'];
        $_SESSION['user_role']  = $employee['role'];

        if ($employee['role'] === 'admin') {
            header("Location: admin_pages/admin_dashboard.php");
        } else {
            header("Location: employee_pages/employee_dashboard.php");
        }
        exit();

    } else {
        $_SESSION['error_password'] = "Incorrect password.";
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="root.css">
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="container">
        <div class="shine"></div>
        <img src="images/hsn_logo_white.png" alt="HSN Logo" class="logo">
        <h2>Sign In</h2>

        <form action="index.php" method="POST">
            <div class="field-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="username"
                    class="<?= $error_email ? 'input-error' : '' ?>"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
                <?php if ($error_email): ?>
                    <span class="field-error">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error_email) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="field-group password-group">
                <label>Password</label>

                <div class="password-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Enter your password"
                        class="<?= $error_password ? 'input-error' : '' ?>"
                    >

                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-fill"></i>
                    </button>
                </div>

                <?php if ($error_password): ?>
                    <span class="field-error">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error_password) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="remember-row">
                <label class="remember-me">
                    <input type="checkbox"> Remember me
                </label>
                <a href="https://www.hsnservice.com/" target="_blank" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="btn-signin">Sign In</button>
        </form>

        <div class="no-account-group">
            <label>Don't have an HSN ID? </label>
            <a href="mailto:Service.Hsnc@hsnservice.com" target="_blank" class="no-id">Contact your HR admin</a>
        </div>
    </div>

<script>
const container = document.querySelector('.container');
const shine = document.querySelector('.shine');

container.addEventListener('mousemove', (e) => {
    const rect = container.getBoundingClientRect();

    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    container.style.setProperty('--x', `${x}px`);
    container.style.setProperty('--y', `${y}px`);
});

container.addEventListener('mouseleave', () => {
    container.style.setProperty('--x', `50%`);
    container.style.setProperty('--y', `50%`);
});

// Password toggle
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';

    // toggle input type
    passwordInput.type = isHidden ? 'text' : 'password';

    // toggle icon
    togglePassword.innerHTML = isHidden
        ? '<i class="bi bi-eye-slash-fill"></i>'
        : '<i class="bi bi-eye-fill"></i>';
});
</script>
</body>
</html>