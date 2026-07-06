<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
require_login();
$user = current_user();
if ($user['role'] !== 'customer') { header('Location: staff-dashboard.php'); exit; }
$stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? OR email = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('is', $user['id'], $user['email']);
$stmt->execute();
$bookings = $stmt->get_result();
render_header('My Account', 'dashboard');
?>
<main class="page-wrap">
<section class="page-hero compact"><div class="container"><span class="crumb">Customer account</span><h1>Hello, <?= e($user['full_name']) ?>.</h1><p>Manage your profile, create new bookings, and track current service requests.</p></div></section>
<section class="section container dashboard-grid">
    <div class="card"><h2>Quick actions</h2><div class="action-list"><a class="btn" href="booking.php">Book a service</a><a class="btn secondary" href="my-bookings.php">View all bookings</a><a class="btn secondary" href="profile.php">Edit profile</a></div></div>
    <div class="card"><h2>Account details</h2><div class="summary-list"><div><span>Email</span><strong><?= e($user['email']) ?></strong></div><div><span>Phone</span><strong><?= e($user['phone'] ?: 'Not added') ?></strong></div></div></div>
</section>
<section class="section container"><div class="card"><div class="section-head left"><span class="eyebrow">Recent bookings</span><h2>Your latest service requests</h2></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Vehicle</th><th>Service</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php while ($b = $bookings->fetch_assoc()): ?><tr><td><?= e(booking_code($b)) ?></td><td><?= e($b['vehicle_model']) ?></td><td><?= e($b['service_type']) ?></td><td><?= e($b['preferred_date']) ?></td><td><span class="badge"><?= e($b['status']) ?></span></td><td><a href="track.php?booking=<?= urlencode(booking_code($b)) ?>&email=<?= urlencode($b['email']) ?>">Track</a></td></tr><?php endwhile; ?></tbody></table></div></div></section>
</main>
<?php render_footer(); ?>
