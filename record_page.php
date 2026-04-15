<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_email'])) {
    // header("Location: index.php");
    // exit();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DTR Project Acer</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="main_page.css">

    <style>
        body::before {
            background-image: url('images/drt_bg.jpg');
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sideBar">
    <a href="index.php">
        <img src="images/drt_sidebar_logo.png" alt="DTR Logo" class="logo">
    </a>
</div>

<!-- TOPBAR -->
<div class="topBar">
    <h3>Employee Dashboard</h3>

    <div class="navUserProfile">
        <i class="bi bi-person-fill"></i>
        <span class="userEmail">
            <?php echo $_SESSION['user_email'] ?? 'user.name@gmail.com'; ?>
        </span>
        <a href="index.php">
            <i class="bi bi-box-arrow-right logoutIcon"></i>
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="mainContent">
    <div class="recordBox">
        <h2>Attendance Records</h2>
        <p>This is where the attendance records will be displayed.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>