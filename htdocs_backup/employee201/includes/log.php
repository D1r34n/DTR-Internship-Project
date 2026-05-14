<?php
function logAction($conn, $admin_id, $action) {
    $stmt = $conn->prepare("INSERT INTO logs (admin_id, action, log_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
}
?>
