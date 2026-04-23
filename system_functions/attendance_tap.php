<?php
/**
 * attendance_tap.php
 * -------------------------------------------------
 * CONTROLLER LAYER (THIN CONTROLLER)
 *
 * Responsibilities ONLY:
 * - Validate session
 * - Receive request input (GPS)
 * - Call business logic layer (attendance_module.php)
 * - Return JSON response
 *
 * IMPORTANT:
 * This file MUST NOT contain:
 * - SQL queries
 * - attendance rules
 * - time computations
 * - business decisions (IN/OUT logic)
 */

session_start();
require_once '../db.php';
require_once 'system_library.php';
require_once 'system_service.php';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');


// -------------------------------------------------
// 1. AUTH CHECK
// -------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$employee_id = $_SESSION['user_id'];

// -------------------------------------------------
// 1.5. CONTROLLER DEBOUNCE (ANTI-SPAM UI PROTECTION)
// -------------------------------------------------
define('TAP_COOLDOWN_SECONDS', 5);

$now = time();
$lastTap = $_SESSION['last_attendance_tap'] ?? 0;

if (($now - $lastTap) < TAP_COOLDOWN_SECONDS) {

    echo json_encode([
        'error' => 'too_fast',
        'seconds_remaining' => TAP_COOLDOWN_SECONDS - ($now - $lastTap)
    ]);
    exit;
}

// update timestamp
$_SESSION['last_attendance_tap'] = $now;

// -------------------------------------------------
// 2. READ INPUT (GPS DATA)
// -------------------------------------------------
$data = json_decode(file_get_contents("php://input"), true);

$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;
$accuracy = $data['accuracy'] ?? null;

if (!$lat || !$lng) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_location']);
    exit;
}


// -------------------------------------------------
// 3. CALL BUSINESS LOGIC LAYER
// -------------------------------------------------
try {

    $result = processAttendanceTap(
        $pdo,
        $employee_id,
        $lat,
        $lng,
        $accuracy
    );

    echo json_encode($result);

} catch (Throwable $e) {

    // Prevent exposing internal errors to frontend
    error_log("Attendance Tap Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'server_error'
    ]);
}