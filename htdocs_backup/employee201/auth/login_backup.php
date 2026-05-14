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
        // ✅ Admin login success
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_logged_in'] = true;

        header("Location: ../admin/dashboard.php");
        exit();
    }

    // ✅ 2. If not admin, check Employee Users
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
        $_SESSION['employee_code'] = $employeeUser['employee_code']; 
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
  <title>Modern Login Page</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: url('https://picsum.photos/1600/900?blur=5') no-repeat center center/cover;
    }
    .login-container {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 40px;
      width: 350px;
      text-align: center;
      color: white;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
      animation: fadeIn 1.2s ease-in-out;
    }
    .login-container h2 {
      margin-bottom: 20px;
      font-size: 28px;
    }
    .login-container input {
      width: 100%;
      padding: 12px;
      margin: 12px 0;
      border: none;
      border-radius: 10px;
      outline: none;
      font-size: 16px;
    }
    .login-container input[type="text"],
    .login-container input[type="password"] {
      background: rgba(255, 255, 255, 0.8);
      color: #111;
    }
    .login-container button {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 10px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      font-size: 18px;
      cursor: pointer;
      margin-top: 15px;
      transition: 0.3s;
    }
    .login-container button:hover {
      background: linear-gradient(135deg, #5563c1, #5c3c8a);
    }
    .extra {
      margin-top: 15px;
      font-size: 14px;
    }
    .extra a {
      color: #fff;
      text-decoration: none;
      font-weight: bold;
    }
    .error {
      background: rgba(255, 0, 0, 0.7);
      padding: 10px;
      border-radius: 10px;
      margin-bottom: 15px;
      font-size: 14px;
      font-weight: bold;
    }
    .privacy-notice {
      margin-top: 20px;
      font-size: 0.8rem;
      text-align: justify;
      background: rgba(0,0,0,0.3);
      padding: 10px;
      border-radius: 8px;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-30px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Welcome Back</h2>

    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="text" name="username" placeholder="Username" autocomplete="username" required>
      <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
      <button type="submit">Login</button>
    </form>

    <div class="extra">
      <p>Forgot your password? <a href="#">Reset</a></p>
      <p>New here? <a href="#">Create Account</a></p>
    </div>

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
      color: #fff;
      font-family: 'Poppins', sans-serif;
  ">
    © 2025 HSNP <br>
    Designed &amp; Developed by: <strong>John Rey Cailo</strong> <br>
    Project Manager: <strong>Anthony Revil</strong>
  </footer>
</body>
</html>
