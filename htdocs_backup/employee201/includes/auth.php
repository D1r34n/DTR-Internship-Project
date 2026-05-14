<?php
// Do NOT call session_start() here — call it once in each page that includes this
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Logs an admin action into the logs table
 *
 * @param mysqli $conn      Database connection
 * @param int    $admin_id  The ID of the admin performing the action
 * @param string $action    The description of the action performed
 */
function logAction($conn, $admin_id, $action) {
    $stmt = $conn->prepare("INSERT INTO logs (admin_id, action, log_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
    $stmt->close();
}
?>
