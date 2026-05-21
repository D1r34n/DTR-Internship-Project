<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

require_once 'db.php';

$quoteText   = trim($_POST['quote_text']   ?? '');
$quoteAuthor = trim($_POST['quote_author'] ?? '');

if ($quoteText === '') {
    echo json_encode(['success' => false, 'message' => 'Quote text is required.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO quote_of_the_day (id, quote_text, quote_author)
        VALUES (1, ?, ?)
        ON DUPLICATE KEY UPDATE quote_text = VALUES(quote_text), quote_author = VALUES(quote_author), updated_at = NOW()
    ");
    $stmt->execute([$quoteText, $quoteAuthor]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
