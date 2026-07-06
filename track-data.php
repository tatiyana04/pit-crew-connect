<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

$bookingInput = trim($_GET['booking'] ?? '');
$emailInput = trim($_GET['email'] ?? '');
$numeric = preg_replace('/[^0-9]/', '', $bookingInput);

if ($bookingInput === '' || $emailInput === '' || $numeric === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing tracking details.']);
    exit;
}

$stmt = $conn->prepare("SELECT b.id, b.booking_code, b.customer_name, b.email, b.service_mode, b.status, b.assigned_staff_id, b.eta_minutes,
        b.customer_lat, b.customer_lng, b.customer_address,
        sc.centre_name, sc.city, sc.address AS centre_address, sc.latitude AS centre_lat, sc.longitude AS centre_lng
    FROM bookings b
    LEFT JOIN service_centres sc ON b.service_centre_id = sc.id
    WHERE (b.booking_code = ? OR b.id = ?) AND b.email = ?
    LIMIT 1");
$stmt->bind_param('sis', $bookingInput, $numeric, $emailInput);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['ok' => false, 'message' => 'Booking not found.']);
    exit;
}

$locStmt = $conn->prepare("SELECT latitude, longitude, eta_minutes, updated_at
    FROM staff_locations
    WHERE booking_id = ?
    ORDER BY updated_at DESC
    LIMIT 1");
$locStmt->bind_param('i', $booking['id']);
$locStmt->execute();
$loc = $locStmt->get_result()->fetch_assoc() ?: null;

$isStale = true;
if ($loc && !empty($loc['updated_at'])) {
    $updatedTs = strtotime($loc['updated_at']);
    $isStale = $updatedTs ? (time() - $updatedTs > 120) : true;
}

echo json_encode([
    'ok' => true,
    'booking' => $booking,
    'employees' => get_booking_employees((int)$booking['id']),
    'location' => $loc,
    'location_is_stale' => $isStale,
    'server_time' => date('c')
]);
?>