<?php
/**
 * save_report_content.php
 * Saves the edited HTML content of an incident report back to the database.
 * Called via AJAX (POST) from incident_report_view.php
 */

session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/auth.php';
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/db.php';

header('Content-Type: application/json');

// ── Only accept POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Read and validate input ────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$report_id = isset($input['report_id']) ? (int)$input['report_id'] : 0;
$html      = $input['html'] ?? '';

if ($report_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid report ID.']);
    exit;
}

// ── Handle reset request ───────────────────────────────────────────────────
$isReset = !empty($input['reset']);

if (!$isReset && empty(trim($html))) {
    echo json_encode(['ok' => false, 'message' => 'HTML content is empty.']);
    exit;
}

if ($isReset) {
    // Clear stored HTML → next page load will auto-generate from DB fields
    $stmt = $conn->prepare("
        UPDATE incident_reports
        SET    custom_html    = NULL,
               custom_html_at = NULL
        WHERE  id = ?
    ");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $report_id);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();
    echo json_encode(['ok' => $ok, 'message' => $ok ? 'Reset successful.' : 'DB error: ' . $conn->error]);
    exit;
}

// ── Basic sanitisation: strip <script> tags to prevent stored XSS ─────────
$html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);

// ── Save to database ───────────────────────────────────────────────────────
$stmt = $conn->prepare("
    UPDATE incident_reports
    SET    custom_html     = ?,
           custom_html_at  = NOW()
    WHERE  id = ?
");

if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("si", $html, $report_id);
$ok = $stmt->execute();

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'DB execute failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$stmt->close();
$conn->close();

echo json_encode([
    'ok'        => true,
    'message'   => 'Content saved successfully.',
    'report_id' => $report_id,
    'saved_at'  => date('F d, Y g:i A'),
]);
exit;