<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_id']) || isset($_SESSION['employee_id'])) {
    if (isset($_SESSION['admin_id'])) {
        header("Location: ../admin/dashboard.php");
    } elseif (isset($_SESSION['employee_id'])) {
        header("Location: ../employee/profile.php");
    }
    exit();
}

require_once __DIR__ . '/../includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // ✅ 1. Check if user is Admin
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

if ($admin && password_verify($password, $admin['password'])) {
    // ✅ Admin / Superadmin / Staff login success
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['role'] = $admin['role'];
    $_SESSION['employee_code'] = $admin['employee_code']; // useful if linked
    $_SESSION['admin_logged_in'] = true;

    // Redirect based on role
    if (in_array($admin['role'], ['superadmin','admin','staff'])) {
        header("Location: ../admin/dashboard.php");
    } elseif ($admin['role'] === 'employee') {
        // fallback in case it's stored here
        header("Location: ../employee/profile.php");
    }
    exit();
}


// ✅ 2. If not admin, check Employee Users (employee_users table)
$stmt = $conn->prepare("
    SELECT eu.*, e.first_name, e.last_name, e.employee_code
    FROM employee_users eu
    INNER JOIN employees e ON eu.employee_code = e.employee_code
    WHERE eu.username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$employeeUser = $result->fetch_assoc();

if ($employeeUser && password_verify($password, $employeeUser['password'])) {
    // ✅ Employee login success
    $_SESSION['employee_id']   = $employeeUser['id']; // from employee_users table
    $_SESSION['employee_code'] = $employeeUser['employee_code']; // matches employees.employee_code
    $_SESSION['employee_name'] = $employeeUser['first_name'] . ' ' . $employeeUser['last_name'];
    $_SESSION['employee_logged_in'] = true;

    header("Location: ../employee/profile.php");
    exit();
}


    // ❌ If both checks failed
    $error = "Invalid username or password!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>

<div class="login-container">
    <div class="logo-group">
        <img src="../assets/img/logo.png" alt="System Logo">
        <img src="../assets/img/logo1.png" alt="Partner Logo">
    </div>
    <h2>Welcome back!</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="input-group">
            <i class="fa fa-user"></i>
            <input type="text" name="username" placeholder="Username" autocomplete="username" required>
        </div>

        <div class="input-group">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
        </div>

        <div style="text-align:right; margin-top: 8px;">
    <a href="forgot_password.php" style="color:#1f2937; font-size:0.9rem; text-decoration:none;">
        Forgot Password?
    </a>
</div>

        <input type="submit" value="Login">
    </form>

    <!-- ✅ Privacy Notice -->
    <div class="privacy-notice">
        By logging in, you acknowledge that you may access employee records containing personal and sensitive information.  
        All data in this system is protected under <strong>Republic Act 10173 (Data Privacy Act of 2012)</strong>.  
        Unauthorized access, misuse, or disclosure of information is strictly prohibited and may be subject to penalties under the law.
    </div>
</div>

<!-- Footer -->
<footer style="
    position: fixed;
    bottom: 10px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 0.85rem;
    color: #9ca3af;
    font-family: 'Segoe UI', Tahoma, sans-serif;
">
    © 2025 HSNP <br>
    Designed &amp; Developed by: <strong>John Rey Cailo</strong> <br>
    Project Manager: <strong>Anthony Revil</strong>
</footer>
</body>
<?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
<div id="timeoutOverlay">
    <div id="timeoutBox">
        <div class="icon">⏰</div>
        <h3>Session Timeout</h3>
        <p>Your session has expired due to inactivity.<br>Please log in again.</p>
        <button onclick="document.getElementById('timeoutOverlay').style.display='none'">
            OK
        </button>
    </div>
</div>

<?php endif; ?>
</html>
