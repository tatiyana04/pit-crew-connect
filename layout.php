<?php
require_once __DIR__ . '/auth.php';

function nav_active($active, $key) {
    return $active === $key ? 'active' : '';
}

function user_first_name($user) {
    if (!$user || empty($user['full_name'])) return 'Account';
    $parts = preg_split('/\s+/', trim($user['full_name']));
    return $parts[0] ?: 'Account';
}

function render_public_nav($active) {
    ?>
    <a class="<?= nav_active($active, 'home') ?>" href="index.php">Home</a>
    <a class="<?= nav_active($active, 'services') ?>" href="services.php">Services</a>
    <a class="<?= nav_active($active, 'packages') ?>" href="packages.php">Packages</a>
    <a class="<?= nav_active($active, 'booking') ?>" href="booking.php">Book Service</a>
    <a class="<?= nav_active($active, 'track') ?>" href="track.php">Track Booking</a>
    <a class="<?= nav_active($active, 'tips') ?>" href="tips.php">Tips</a>
    <a class="<?= nav_active($active, 'about') ?>" href="about.php">About</a>
    <a class="<?= nav_active($active, 'contact') ?>" href="contact.php">Contact</a>
    <?php
}

function render_customer_nav($active, $user) {
    ?>
    <a class="<?= nav_active($active, 'dashboard') ?>" href="customer-dashboard.php">Dashboard</a>
    <a class="<?= nav_active($active, 'booking') ?>" href="booking.php">Book Service</a>
    <a class="<?= nav_active($active, 'my-bookings') ?>" href="my-bookings.php">My Bookings</a>
    <a class="<?= nav_active($active, 'track') ?>" href="track.php">Track Booking</a>
    <a class="<?= nav_active($active, 'tips') ?>" href="tips.php">Tips</a>
    <a class="<?= nav_active($active, 'profile') ?>" href="profile.php">Profile</a>
    <a class="btn small logout-btn" href="<?= e(logout_url('customer')) ?>">Logout</a>
    <?php
}

function render_staff_nav($active, $user) {
    ?>
    <a class="<?= nav_active($active, 'staff-dashboard') ?>" href="staff-dashboard.php">Dashboard</a>
    <a class="<?= nav_active($active, 'staff-bookings') ?>" href="staff-bookings.php">Bookings</a>
    <a class="<?= nav_active($active, 'staff-employees') ?>" href="staff-employees.php">Employees</a>
    <a class="<?= nav_active($active, 'staff-messages') ?>" href="staff-messages.php">Messages</a>
    <a class="<?= nav_active($active, 'staff-content') ?>" href="staff-content.php">Services & Content</a>
    <a class="<?= nav_active($active, 'staff-centres') ?>" href="staff-centres.php">Service Centres</a>
    <a href="index.php">View Website</a>
    <a class="btn small logout-btn" href="<?= e(logout_url('staff')) ?>">Logout</a>
    <?php
}

function render_header($title = 'PitCrew Connect', $active = '', $options = []) {
    $user = current_user();
    $bodyClass = $options['body_class'] ?? '';
    $role = $user['role'] ?? 'guest';
    $isStaff = $user && in_array($role, ['admin', 'staff'], true);
    $isCustomer = $user && $role === 'customer';
    $showSplash = ($active === 'home' && !$isStaff && empty($options['hide_splash']));

    if ($isStaff) {
        $stripLeft = '🔧 Staff workspace';
        $stripRight = 'Manage bookings • Assign employees • Reply to customers • Update content';
    } elseif ($isCustomer) {
        $stripLeft = '👋 Welcome, ' . user_first_name($user);
        $stripRight = 'Book services • Track progress • Manage your profile';
    } else {
        $stripLeft = '🏁 Motorsport-inspired vehicle service booking';
        $stripRight = 'Fast requests • Clear status updates • Mobile service options';
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | PitCrew Connect</title>
    <?php $cssVersion = file_exists(__DIR__ . '/pitcrew-ui.css') ? filemtime(__DIR__ . '/pitcrew-ui.css') : time(); ?>
    <link rel="stylesheet" href="pitcrew-ui.css?v=<?= $cssVersion ?>">
    <script>
        window.PITCREW_CONFIG = {
            mapsKey: <?= json_encode(defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '') ?>,
            googleClientId: <?= json_encode(defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '') ?>
        };
    </script>
    <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?> <?= $isStaff ? 'staff-session' : ($isCustomer ? 'customer-session' : 'guest-session') ?>">
<?php
if ($showSplash && file_exists(__DIR__ . '/splash.php')) {
    include __DIR__ . '/splash.php';
}
?>
<header class="site-header <?= $isStaff ? 'staff-header' : '' ?>">
    <div class="top-strip">
        <div class="container top-strip-inner">
            <span><?= e($stripLeft) ?></span>
            <span><?= e($stripRight) ?></span>
        </div>
    </div>
    <nav class="navbar container">
        <a class="brand" href="<?= $isStaff ? 'staff-dashboard.php' : 'index.php' ?>" aria-label="PitCrew Connect home">
            <span class="brand-icon">▦</span>
            <span>PitCrew Connect</span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Open menu">☰</button>
        <div class="nav-links">
            <?php if ($isStaff): ?>
                <?php render_staff_nav($active, $user); ?>
            <?php elseif ($isCustomer): ?>
                <?php render_customer_nav($active, $user); ?>
            <?php else: ?>
                <?php render_public_nav($active); ?>
                <a class="btn small" href="login.php">Sign In</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
    <?php
}

function render_footer() {
    $user = current_user();
    $role = $user['role'] ?? 'guest';
    $isStaff = $user && in_array($role, ['admin', 'staff'], true);
    ?>
<footer class="site-footer <?= $isStaff ? 'staff-footer' : '' ?>">
    <div class="container footer-grid">
        <div>
            <h3>PitCrew Connect</h3>
            <?php if ($isStaff): ?>
                <p>Unified staff workspace for bookings, employees, customer messages, website content, and service locations.</p>
            <?php else: ?>
                <p>Vehicle servicing made easier with online bookings, service centre selection, mobile service options, and clear progress updates.</p>
            <?php endif; ?>
        </div>
        <?php if ($isStaff): ?>
            <div>
                <h4>Staff Workspace</h4>
                <a href="staff-dashboard.php">Dashboard</a>
                <a href="staff-bookings.php">Bookings</a>
                <a href="staff-employees.php">Employees</a>
                <a href="staff-messages.php">Messages</a>
            </div>
            <div>
                <h4>Content</h4>
                <a href="staff-content.php">Services & Content</a>
                <a href="staff-centres.php">Service Centres</a>
                <a href="<?= e(logout_url('staff')) ?>">Logout</a>
            </div>
        <?php else: ?>
            <div>
                <h4>Customer</h4>
                <a href="booking.php">Book Service</a>
                <a href="track.php">Track Booking</a>
                <a href="packages.php">Packages</a>
                <a href="tips.php">Car Care Tips</a>
            </div>
            <div>
                <h4>Support</h4>
                <a href="contact.php">Contact PitCrew</a>
                <a href="about.php">About PitCrew</a>
                <?php if ($user): ?>
                    <a href="profile.php">My Profile</a>
                    <a href="<?= e(logout_url('customer')) ?>">Logout</a>
                <?php else: ?>
                    <a href="login.php">Customer Sign In</a>
                    <a href="staff-login.php">Staff Sign In</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="container footer-bottom">© <?= date('Y') ?> PitCrew Connect. All rights reserved.</div>
</footer>
<?php $jsVersion = file_exists(__DIR__ . '/app.js') ? filemtime(__DIR__ . '/app.js') : time(); ?>
<script src="app.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
    <?php
}
?>