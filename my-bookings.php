<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
require_login();
$user = current_user();
$stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? OR email = ? ORDER BY created_at DESC");
$stmt->bind_param('is', $user['id'], $user['email']);
$stmt->execute();
$bookings = $stmt->get_result();
render_header('My Bookings', 'my-bookings');
?>
<main class="page-wrap">
<section class="page-hero compact"><div class="container"><span class="crumb">My Account / Bookings</span><h1>My bookings</h1><p>All booking requests linked to your account email.</p></div></section>
<section class="section container"><div class="card"><div class="table-wrap"><table><thead><tr><th>ID</th><th>Vehicle</th><th>Service</th><th>Package</th><th>Date</th><th>Status</th><th></th></tr></thead><tbody><?php while ($b = $bookings->fetch_assoc()): ?><tr><td><?= e(booking_code($b)) ?></td><td><?= e($b['vehicle_model']) ?></td><td><?= e($b['service_type']) ?></td><td><?= e($b['package_type']) ?></td><td><?= e($b['preferred_date']) ?></td><td><span class="badge"><?= e($b['status']) ?></span></td><td><a href="track.php?booking=<?= urlencode(booking_code($b)) ?>&email=<?= urlencode($b['email']) ?>">Track</a></td></tr><?php endwhile; ?></tbody></table></div></div></section>
</main>
<?php render_footer(); ?>
