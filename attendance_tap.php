<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$employee_id = $_SESSION['user_id'];

// --------------------
// OFFICE LOCATION
// --------------------
$officeLat = 14.584415691940826;
$officeLng = 120.99562631352414;
$radius = 100;

// --------------------
// GPS INPUT (from frontend)
// --------------------
$data = json_decode(file_get_contents("php://input"), true);

$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;
$accuracy = $data['accuracy'] ?? null;

// fallback safety
if (!$lat || !$lng) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_location']);
    exit;
}

// --------------------
// DISTANCE FUNCTION
// --------------------
function distanceMeters($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earthRadius * $c;
}

$distance = distanceMeters($lat, $lng, $officeLat, $officeLng);
$isWithin = ($distance <= $radius) ? 1 : 0;

// --------------------
// GET LAST LOG
// --------------------
$stmt = $pdo->prepare("
    SELECT log_type
    FROM logs
    WHERE employee_id = ?
    ORDER BY log_time DESC
    LIMIT 1
");
$stmt->execute([$employee_id]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

// --------------------
// DETERMINE IN/OUT
// --------------------
if (!$lastLog || $lastLog['log_type'] === 'OUT') {
    $nextType = 'IN';
    $response = 'timed_in';
} else {
    $nextType = 'OUT';
    $response = 'timed_out';
}

// --------------------
// INSERT FULL LOG
// --------------------
$stmt = $pdo->prepare("
    INSERT INTO logs (
        employee_id,
        log_type,
        log_time,
        latitude,
        longitude,
        accuracy,
        is_within_office,
        distance_meters
    )
    VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)
");

$stmt->execute([
    $employee_id,
    $nextType,
    $lat,
    $lng,
    $accuracy,
    $isWithin,
    $distance
]);

// --------------------
// RESPONSE
// --------------------
echo json_encode([
    'tap' => $response,
    'log_type' => $nextType,
    'distance_meters' => round($distance, 2),
    'is_within_office' => $isWithin
]);