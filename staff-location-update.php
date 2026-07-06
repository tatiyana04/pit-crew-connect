<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/auth.php';
require_staff();

header('Content-Type: application/json');

if (!is_staff_user()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Staff login required.']);
    exit;
}

$user = current_user();
$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$csrf = $payload['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid request token. Refresh the page and try again.']);
    exit;
}

$bookingId = (int)($payload['booking_id'] ?? 0);
$lat = $payload['latitude'] ?? null;
$lng = $payload['longitude'] ?? null;
$eta = isset($payload['eta_minutes']) ? (int)$payload['eta_minutes'] : 30;
$eta = max(0, min(240, $eta));

if (
    $bookingId <= 0 ||
    !is_numeric($lat) ||
    !is_numeric($lng) ||
    (float)$lat < -90 ||
    (float)$lat > 90 ||
    (float)$lng < -180 ||
    (float)$lng > 180 ||
    abs((float)$lat) < 0.000001 ||
    abs((float)$lng) < 0.000001
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing or invalid booking/location values.']);
    exit;
}

$lat = (float)$lat;
$lng = (float)$lng;

$stmt = $conn->prepare("SELECT id, status FROM bookings WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $bookingId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Booking not found.']);
    exit;
}

$up = $conn->prepare("INSERT INTO staff_locations (booking_id, staff_id, latitude, longitude, eta_minutes)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        latitude = VALUES(latitude),
        longitude = VALUES(longitude),
        eta_minutes = VALUES(eta_minutes),
        updated_at = CURRENT_TIMESTAMP");
$up->bind_param('iiddi', $bookingId, $user['id'], $lat, $lng, $eta);
$up->execute();

$status = 'On the Way';
$update = $conn->prepare("UPDATE bookings
    SET status = ?, eta_minutes = ?
    WHERE id = ?
      AND status IN ('Pending','Confirmed','Team Assigned','On the Way')");
$update->bind_param('sii', $status, $eta, $bookingId);
$update->execute();

echo json_encode([
    'ok' => true,
    'message' => 'Service team location updated.',
    'booking_id' => $bookingId,
    'status' => $status,
    'latitude' => $lat,
    'longitude' => $lng,
    'eta_minutes' => $eta,
    'updated_at' => date('Y-m-d H:i:s')
]);
?>