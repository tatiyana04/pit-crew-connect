<?php
// PitCrew Connect database configuration.
// IMPORTANT: Do not show this file in screenshots or videos.

$host = "add";
$user = "add";
$password = "add";
$database = "add";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    error_log("PitCrew DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    die("Service is temporarily unavailable. Please try again later.");
}

$conn->set_charset("utf8mb4");

// Phase 3 optional Google features.
// GOOGLE_MAPS_API_KEY enables interactive maps.
// GOOGLE_CLIENT_ID enables Continue with Google on customer login/signup.
define('GOOGLE_MAPS_API_KEY', '');
define('GOOGLE_CLIENT_ID', '');

define('PITCREW_SITE_NAME', 'PitCrew Connect');
?>
