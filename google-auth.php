<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '') {
    echo json_encode(['ok' => false, 'message' => 'Google Sign-In is not configured.']);
    exit;
}
$payload = json_decode(file_get_contents('php://input'), true);
$idToken = $payload['credential'] ?? '';
if (!$idToken) { echo json_encode(['ok' => false]); exit; }
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
$response = @file_get_contents($verifyUrl);
if (!$response) { echo json_encode(['ok' => false, 'message' => 'Unable to verify Google account.']); exit; }
$data = json_decode($response, true);
if (($data['aud'] ?? '') !== GOOGLE_CLIENT_ID || empty($data['email'])) {
    echo json_encode(['ok' => false, 'message' => 'Google account verification failed.']);
    exit;
}
$email = strtolower($data['email']);
$fullName = $data['name'] ?? $email;
$googleId = $data['sub'] ?? '';
$userRow = find_user_by_email($email);
if (!$userRow) {
    $role = 'customer';
    $provider = 'google';
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, role, auth_provider, google_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssss', $fullName, $email, $role, $provider, $googleId);
    $stmt->execute();
    $userId = $stmt->insert_id;
    $profile = $conn->prepare("INSERT INTO customer_profiles (user_id) VALUES (?)");
    $profile->bind_param('i', $userId);
    $profile->execute();
    $userRow = ['id' => $userId, 'role' => 'customer'];
}
login_user($userRow);
echo json_encode(['ok' => true, 'redirect' => $userRow['role'] === 'customer' ? 'customer-dashboard.php' : 'staff-dashboard.php']);
?>
