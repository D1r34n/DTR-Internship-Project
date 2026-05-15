<?php
session_start();

if (isset($_SESSION['user_id'])) {

    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin_pages/dashboard_page.php");
        exit();
    }

    header("Location: employee_pages/employee_dashboard.php");
    exit();
}

// If not logged in → go to login page
header("Location: authentication_pages/login.php");
exit();