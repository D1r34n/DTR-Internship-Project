<?php
// list_models.php
header("Content-Type: application/json");

$apiKey = "AIzaSyBPXKvvQrLrbVd2GhKdZIGsdrLtScehqdI";
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CAINFO => "C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem" // added path
]);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$response) {
    echo json_encode(["error" => $err]);
} else {
    echo $response;
}
