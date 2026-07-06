<?php
require_once __DIR__ . '/config.php';

/*
 * PitCrew Connect session isolation
 * ---------------------------------
 * Same Chrome browser tabs share cookies.
 * Therefore customer/student and staff logins must use different PHP session cookies.
 *
 * Customer/student cookie: PITCREW_CUSTOMERSESSID
 * Staff cookie:            PITCREW_STAFFSESSID
 */

function pitcrew_normalise_context($context) {
    $context = strtolower(trim((string)$context));

    if (in_array($context, ['staff', 'admin'], true)) {
        return 'staff';
    }

    if (in_array($context, ['customer', 'student', 'user'], true)) {
        return 'customer';
    }

    return '';
}

function pitcrew_requested_context() {
    if (defined('PITCREW_SESSION_CONTEXT')) {
        $forced = pitcrew_normalise_context(PITCREW_SESSION_CONTEXT);
        if ($forced !== '') {
            return $forced;
        }
    }

    $requested = pitcrew_normalise_context($_GET['type'] ?? $_POST['type'] ?? $_REQUEST['session_type'] ?? '');
    if ($requested !== '') {
        return $requested;
    }

    $script = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));

    $staffPages = [
        'staff-login.php',
        'staff-dashboard.php',
        'staff-bookings.php',
        'staff-employees.php',
        'staff-messages.php',
        'staff-content.php',
        'staff-centres.php',
        'staff-jobs.php',
        'staff-location-update.php',
        'admin-login.php',
        'admin-dashboard.php',
        'admin-bookings.php',
        'admin-centres.php',
        'bookings.php'
    ];

    $customerPages = [
        'login.php',
        'signup.php',
        'google-auth.php',
        'customer-dashboard.php',
        'my-bookings.php',
        'profile.php',
        'booking.php',
        'submit.php',
        'track.php',
        'track-data.php',
        'index.php',
        'about.php',
        'contact.php',
        'services.php',
        'packages.php',
        'tips.php',
        'service-centres-data.php'
    ];

    if (in_array($script, $staffPages, true) || strpos($script, 'staff-') === 0) {
        return 'staff';
    }

    if (in_array($script, $customerPages, true)) {
        return 'customer';
    }

    return 'customer';
}

function pitcrew_session_cookie_name($context) {
    return $context === 'staff' ? 'PITCREW_STAFFSESSID' : 'PITCREW_CUSTOMERSESSID';
}

function pitcrew_start_session() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $context = pitcrew_requested_context();

    session_name(pitcrew_session_cookie_name($context));

    $isSecureRequest =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecureRequest,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    $_SESSION['pitcrew_session_context'] = $context;
}

pitcrew_start_session();

function current_session_context() {
    return $_SESSION['pitcrew_session_context'] ?? pitcrew_requested_context();
}

function logout_url($context = null) {
    $context = pitcrew_normalise_context($context ?: current_session_context());

    if ($context === '') {
        $context = 'customer';
    }

    return 'logout.php?type=' . rawurlencode($context);
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('Invalid request token. Please go back and try again.');
        }
    }
}

function app_table_exists($table) {
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();

    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function app_column_exists($table, $column) {
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function current_user() {
    global $conn;

    static $cachedByContext = [];
    $context = current_session_context();

    if (array_key_exists($context, $cachedByContext)) {
        return $cachedByContext[$context];
    }

    if (empty($_SESSION['user_id'])) {
        $cachedByContext[$context] = false;
        return false;
    }

    $stmt = $conn->prepare("SELECT id, full_name, email, phone, role, auth_provider, created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc() ?: false;

    if ($user && $context === 'customer' && $user['role'] !== 'customer') {
        $cachedByContext[$context] = false;
        return false;
    }

    if ($user && $context === 'staff' && !in_array($user['role'], ['admin', 'staff'], true)) {
        $cachedByContext[$context] = false;
        return false;
    }

    $cachedByContext[$context] = $user;
    return $user;
}

function is_logged_in() {
    return current_user() !== false;
}

function is_staff_user() {
    $user = current_user();
    return $user && in_array($user['role'], ['admin', 'staff'], true);
}

function is_admin_user() {
    return is_staff_user();
}

function require_login($redirect = 'login.php') {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

function require_customer() {
    require_login('login.php');

    $user = current_user();

    if (!$user || $user['role'] !== 'customer') {
        header('Location: login.php');
        exit;
    }
}

function require_staff() {
    require_login('staff-login.php');

    if (!is_staff_user()) {
        http_response_code(403);
        echo '<!doctype html><html><head><title>Access denied</title><link rel="stylesheet" href="pitcrew-ui.css"></head><body><main class="page-wrap"><section class="section container"><div class="card"><h1>Access denied</h1><p>This area is available only for authorised PitCrew staff.</p><a class="btn" href="staff-login.php">Staff sign in</a></div></section></main></body></html>';
        exit;
    }
}

function require_admin() {
    require_staff();
}

function redirect_after_login($role) {
    if (in_array($role, ['admin', 'staff'], true)) {
        header('Location: staff-dashboard.php');
    } else {
        header('Location: customer-dashboard.php');
    }

    exit;
}

function login_user($userRow) {
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$userRow['id'];
    $_SESSION['user_role'] = $userRow['role'];
    $_SESSION['pitcrew_session_context'] = current_session_context();
}

function logout_user() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function find_user_by_email($email) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function booking_code($row) {
    if (!empty($row['booking_code'])) {
        return $row['booking_code'];
    }

    return 'PC-' . (int)$row['id'];
}

function get_booking_employees($bookingId) {
    global $conn;

    $employees = [];

    if (!app_table_exists('booking_employees') || !app_table_exists('employees')) {
        return $employees;
    }

    $stmt = $conn->prepare("SELECT e.* FROM booking_employees be JOIN employees e ON e.id = be.employee_id WHERE be.booking_id = ? ORDER BY e.full_name");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $employees[] = $row;
    }

    return $employees;
}

function get_booking_employee_names($bookingId) {
    $employees = get_booking_employees($bookingId);

    if (!$employees) {
        return 'Not assigned';
    }

    return implode(', ', array_map(fn($e) => $e['full_name'], $employees));
}
?>