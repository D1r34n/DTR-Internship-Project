<?php
// ── Buffer ALL output so any PHP warnings/errors don't break JSON ──────
ob_start();

session_start();

// ── Force JSON response no matter what happens below ───────────────────
function jsonExit(bool $ok, string $message): void {
    ob_clean(); // discard any stray output (warnings, HTML from includes)
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

// ── Catch fatal errors and return JSON instead of HTML ──────────────────
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Server error: ' . $err['message']]);
    }
});

// ── Include DB only (auth.php may output HTML redirects — skip it) ──────
include $_SERVER['DOCUMENT_ROOT'] . '/employee201/includes/db.php';

// ── Auth check manually (don't rely on auth.php for AJAX) ───────────────
if (!isset($_SESSION['admin_id'])) {
    jsonExit(false, 'Not authenticated. Please log in again.');
}

// ── Only accept POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(false, 'Invalid request method');
}

// ── Read JSON body ────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['report_id'])) {
    jsonExit(false, 'Missing report_id');
}

$report_id = (int)$data['report_id'];
$admin_id  = (int)$_SESSION['admin_id'];
$is_reset  = !empty($data['reset']);

if ($report_id <= 0) {
    jsonExit(false, 'Invalid report_id');
}

// ── Verify DB connection ──────────────────────────────────────────────────
if (!isset($conn) || !$conn) {
    jsonExit(false, 'Database connection failed');
}

// ── Verify report exists ──────────────────────────────────────────────────
$check = $conn->prepare("SELECT id FROM incident_reports WHERE id = ?");
if (!$check) {
    jsonExit(false, 'DB prepare failed: ' . $conn->error);
}
$check->bind_param("i", $report_id);
$check->execute();
$found = $check->get_result()->fetch_assoc();
$check->close();

if (!$found) {
    jsonExit(false, 'Report not found');
}

// ════════════════════════════════════════════════════════════════════════
//  RESET — clear custom_html, revert to auto-generated
// ════════════════════════════════════════════════════════════════════════
if ($is_reset) {
    $stmt = $conn->prepare("
        UPDATE incident_reports
        SET    custom_html    = NULL,
               custom_html_at = NULL,
               updated_by     = ?,
               updated_at     = NOW()
        WHERE  id = ?
    ");
    if (!$stmt) jsonExit(false, 'Prepare failed: ' . $conn->error);

    $stmt->bind_param("ii", $admin_id, $report_id);
    $ok  = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();
    $conn->close();

    if ($ok) jsonExit(true, 'Report reset to original');
    jsonExit(false, 'Database error: ' . $err);
}

// ════════════════════════════════════════════════════════════════════════
//  SAVE — store edited HTML
// ════════════════════════════════════════════════════════════════════════
$html = trim($data['html'] ?? '');

if ($html === '') {
    jsonExit(false, 'No HTML content provided');
}

$stmt = $conn->prepare("
    UPDATE incident_reports
    SET    custom_html    = ?,
           custom_html_at = NOW(),
           updated_by     = ?,
           updated_at     = NOW()
    WHERE  id = ?
");
if (!$stmt) jsonExit(false, 'Prepare failed: ' . $conn->error);

$stmt->bind_param("sii", $html, $admin_id, $report_id);
$ok  = $stmt->execute();
$err = $stmt->error;
$stmt->close();
$conn->close();

if ($ok) jsonExit(true, 'Edits saved successfully');
jsonExit(false, 'Database error: ' . $err);