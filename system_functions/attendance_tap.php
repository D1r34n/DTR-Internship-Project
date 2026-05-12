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

// ── TESTING MODE (localhost only) ──
// Flip to true to bypass GPS, debounce, and schedule window checks
$testing_mode = false;

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
// 2. READ INPUT (GPS DATA)
// Must be read first so $data is available for all checks below
// -------------------------------------------------
$data     = json_decode(file_get_contents("php://input"), true);
$lat      = $data['lat']      ?? null;
$lng      = $data['lng']      ?? null;
$accuracy = $data['accuracy'] ?? null;
$photoB64 = $data['photo']    ?? null;

// Save webcam capture if provided
$photoPath = null;
if ($photoB64 && preg_match('/^data:image\/jpeg;base64,/', $photoB64)) {
    $imageData = base64_decode(substr($photoB64, strpos($photoB64, ',') + 1));
    if ($imageData !== false) {
        $captureDir = __DIR__ . '/../assets/attendance_captures/';
        $filename   = 'cap_' . $_SESSION['user_id'] . '_' . date('Ymd_His') . '.jpg';
        if (file_put_contents($captureDir . $filename, $imageData) !== false) {
            $photoPath = $filename;
        }
    }
}

// Only allow simulated time and testing mode on localhost
$isLocalhost  = $_SERVER['SERVER_NAME'] === 'localhost';
$simulatedNow = null;

if ($isLocalhost) {
    $simulatedNow = $data['simulated_now'] ?? null;
}

// -------------------------------------------------
// 2.5. CONTROLLER DEBOUNCE
// -------------------------------------------------
if (!$testing_mode || !$isLocalhost) {
    define('TAP_COOLDOWN_SECONDS', 5);
    $now     = time();
    $lastTap = $_SESSION['last_attendance_tap'] ?? 0;

    if (($now - $lastTap) < TAP_COOLDOWN_SECONDS) {
        echo json_encode([
            'error'             => 'too_fast',
            'seconds_remaining' => TAP_COOLDOWN_SECONDS - ($now - $lastTap)
        ]);
        exit;
    }

    $_SESSION['last_attendance_tap'] = $now;
}

// Skip GPS requirement when in testing mode
if ((!$lat || !$lng) && (!$testing_mode || !$isLocalhost)) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_location']);
    exit;
}

// -------------------------------------------------
// 3. CALL BUSINESS LOGIC LAYER
// -------------------------------------------------
try {

    // ── BREAK IN / OUT — after debounce ──
    if (!empty($data['break_tap'])) {
        $result = processBreakTap($pdo, $employee_id, $lat, $lng, $accuracy, $simulatedNow);
        $result['env'] = [
            'is_localhost'  => $isLocalhost,
            'testing_mode'  => $testing_mode && $isLocalhost,
            'simulated_now' => $simulatedNow ?? 'none',
        ];
        echo json_encode($result);
        exit;
    }

    if ($testing_mode && $isLocalhost) {
        $now    = $simulatedNow ?? date('Y-m-d H:i:s');
        $result = processAttendanceTapTest($pdo, $employee_id, $now);
    } else {
        $result = processAttendanceTap($pdo, $employee_id, $lat, $lng, $accuracy, $simulatedNow, $photoPath);
    }

    $result['env'] = [
        'is_localhost'  => $isLocalhost,
        'testing_mode'  => $testing_mode && $isLocalhost,
        'simulated_now' => $simulatedNow ?? 'none',
    ];

    echo json_encode($result);

} catch (Throwable $e) {
    error_log("Attendance Tap Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}