<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

if (!isset($_FILES['profile_image'])) {
    echo json_encode(['error' => 'no_file']);
    exit();
}

$file = $_FILES['profile_image'];

// Validate MIME type
$allowed = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($file['type'], $allowed)) {
    echo json_encode(['error' => 'invalid_type']);
    exit();
}

// Create directory
$dir = '../assets/user_profiles/';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

/* =========================================================
   FIXED FILENAME (YOUR REQUIREMENT)
========================================================= */
$filename = 'avatar_user_' . $userId . '.png';
$target   = $dir . $filename;

/* =========================================================
   OPTIONAL: remove old file if exists (recommended)
========================================================= */
if (file_exists($target)) {
    unlink($target);
}

/* =========================================================
   Convert all uploads to PNG (important consistency fix)
   - avoids jpg/webp mismatch with fixed .png name
========================================================= */
switch ($file['type']) {

    case 'image/jpeg':
        $img = imagecreatefromjpeg($file['tmp_name']);
        break;

    case 'image/png':
        $img = imagecreatefrompng($file['tmp_name']);
        break;

    case 'image/webp':
        $img = imagecreatefromwebp($file['tmp_name']);
        break;

    default:
        echo json_encode(['error' => 'invalid_type']);
        exit();
}

// Save as PNG
imagepng($img, $target);
imagedestroy($img);

/* =========================================================
   Update DB
========================================================= */
$stmt = $pdo->prepare("UPDATE employees SET profile_image = ? WHERE id = ?");
$stmt->execute([$filename, $userId]);

/* =========================================================
   Update session
========================================================= */
$_SESSION['profile_image'] = $filename;

echo json_encode([
    'success'  => true,
    'filename' => $filename
]);