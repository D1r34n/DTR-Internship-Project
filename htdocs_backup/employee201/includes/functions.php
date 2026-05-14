function logAction($conn, $action, $user = 'admin') {
    $stmt = $conn->prepare("INSERT INTO logs (action, performed_by) VALUES (?, ?)");
    $stmt->execute([$action, $user]);
}
