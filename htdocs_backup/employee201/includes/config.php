<?php
// Load environment variables from .env file
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // skip comments
        [$k, $v] = explode('=', $line, 2) + [null, null];
        if ($k) $_ENV[trim($k)] = trim($v);
    }
}

// Assign DB settings from environment or use defaults
$host = "localhost";
$user = "root";
$password = "";
$dbname = "e201";

// Create MySQLi connection
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
