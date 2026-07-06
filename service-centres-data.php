<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$centres = [];
$res = $conn->query("SELECT id, centre_name, city, address, phone, latitude, longitude, opening_hours FROM service_centres WHERE is_active=1 ORDER BY city, centre_name");
while ($r = $res->fetch_assoc()) $centres[] = $r;
echo json_encode(['centres' => $centres]);
?>
