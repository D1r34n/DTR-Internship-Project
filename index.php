<?php
session_start();
require_once 'db.php';

$bg_image = "images/drt_bg.jpg";
$page_title = "HSN DRT System";
$logo = "images/HSN.png";
$error = "";

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email and password are required.";
    } else {
        // Check employee in database
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee && password_verify($password, $employee['password'])) {
            $_SESSION['user_email'] = $employee['email'];
            $_SESSION['user_name'] = $employee['name'];
            $_SESSION['user_id'] = $employee['id'];
            header("Location: main_page.php");
            exit();
        } else {
            $_SESSION['error'] = "Wrong Email/Password";
        }
    }

    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="index.css">
    <style>
        body::before {
            background-image: url('<?php echo $bg_image; ?>');
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="<?php echo $logo; ?>" alt="HSN Logo" class="logo">
        <h2>Sign In</h2>
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="field-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email">
            </div>

            <div class="field-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password">
            </div>

            <div class="remember-row">
                <label class="remember-me">
                    <input type="checkbox"> Remember me
                </label>
                <a href="https://www.hsnservice.com/" target="_blank" class="forgot-password"><u>Forgot password?</u></a>
            </div>

            <button type="submit" class="btn-signin">Sign In</button>
        </form>

        <div class="no-account-group">
            <label>Don't have an HSN ID? </label>
            <a href="mailto:Service.Hsnc@hsnservice.com" target="_blank" style="color: #97be41; text-decoration: none;"><u>Contact your HR admin</u></a>
        </div>
    </div>
</body>
</html>