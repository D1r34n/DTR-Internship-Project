<?php
session_start();
$bg_image = "images/drt_bg.jpg";
$page_title = "DTR Project Acer";
$logo = "images/HSN.png";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // RESTRICTIONS
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $_SESSION['user_email'] = $email;
        header("Location: main_page.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
    <div class="error-msg">
        <?php echo $error; ?>
    </div>
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
            <a href="https://www.hsnservice.com/" target="_blank" style="color: #97be41; text-decoration: none;"><u>Contact your HR admin</u></a>
        </div>
    </div>

</body>
</html>