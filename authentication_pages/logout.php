<?php
session_start();

session_unset();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

$redirect = isset($_GET['timeout']) ? 'login.php?timeout=1' : 'login.php';
header("Location: $redirect");
exit();
?>