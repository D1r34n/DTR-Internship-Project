<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: authentication_pages/login.php");
    exit();
}

// always go to dashboard (NO ROLE LOGIC HERE)
header("Location: regular_pages/dashboard_page.php");
exit();