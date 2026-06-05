<?php
$host     = 'localhost';
$dbname   = 'dtr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/* ------------------------------------------------
   Session Timeout — 30 minutes of inactivity
   ------------------------------------------------ */
define('SESSION_TIMEOUT', 180);  // 30 minutes

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_SERVER['HTTP_X_KEEPALIVE']);
        if ($isAjax) {
            session_unset();
            session_destroy();
            header('Content-Type: application/json');
            echo json_encode(['expired' => true]);
        } else {
            header('Location: /DTR-Internship-Project/authentication_pages/logout.php?timeout=1');
        }
        exit();
    }
    $_SESSION['last_activity'] = time();
}
?>