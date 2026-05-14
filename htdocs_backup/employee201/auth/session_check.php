<?php


$timeout = 900; // 15 minutes in seconds

if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $timeout) {
        session_unset();
        session_destroy();
        
        // Return JSON if it's an AJAX check
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            echo json_encode(['timeout' => true]);
            exit();
        }
        
        // Otherwise redirect with a flag
        header("Location: ../auth/login.php?timeout=1");
        exit();
    }
}

$_SESSION['last_activity'] = time();
?>