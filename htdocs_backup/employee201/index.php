<?php
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ../admin/index.php");
    exit();
} else {
    header("Location: auth/login.php");
    exit();
}
