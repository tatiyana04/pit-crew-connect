<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['centre_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $lat = (float)($_POST['latitude'] ?? 0);
    $lng = (float)($_POST['longitude'] ?? 0);
    $hours = trim($_POST['opening_hours'] ?? 'Mon-Sat, 8.00 AM - 6.00 PM');
    if ($name && $city && $address && $lat && $lng) {
        $stmt = $conn->prepare("INSERT INTO service_centres (centre_name, city, address, phone, latitude, longitude, opening_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssdds', $name, $city, $address, $phone, $lat, $lng, $hours);
        $stmt->execute();
        $message = 'Service centre added.';
    }
}

$centres = $conn->query("SELECT * FROM service_centres ORDER BY city, centre_name");
render_header('Service Centres', 'staff-centres');
?>

<main class="page-wrap staff-page staff-directory-page">
    <section class="page-hero compact staff-directory-hero">
        <div class="container">
            <span class="crumb">Staff Area / Centres</span>
            <h1>Service centres</h1>
            <p>Manage the service centre locations shown to customers during booking and mobile service selection.</p>
        </div>
    </section>

    <section class="section container staff-directory-layout">
        <form class="card form-card staff-directory-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="staff-form-kicker">Location setup</div>
            <h2>Add service centre</h2>
            <p class="staff-form-note">Add accurate location details so customers can select the nearest PitCrew service point.</p>

            <?php if($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

            <label>Centre name
                <input name="centre_name" required placeholder="PitCrew Colombo">
            </label>

            <label>City
                <input name="city" required placeholder="Colombo">
            </label>

            <label>Address
                <textarea name="address" rows="3" required placeholder="Full centre address"></textarea>
            </label>

            <label>Phone
                <input name="phone" placeholder="0112345678">
            </label>

            <div class="form-grid">
                <label>Latitude
                    <input name="latitude" required placeholder="6.927079">
                </label>
                <label>Longitude
                    <input name="longitude" required placeholder="79.861244">
                </label>
            </div>

            <label>Opening hours
                <input name="opening_hours" value="Mon-Sat, 8.00 AM - 6.00 PM">
            </label>

            <button class="btn" type="submit">Add Centre</button>
        </form>

        <div class="staff-directory-panel">
            <div class="staff-directory-head">
                <div>
                    <span class="eyebrow">Centre network</span>
                    <h2>Current centres</h2>
                    <p>These centres appear in the customer booking flow and nearest centre selection.</p>
                </div>
                <span class="staff-directory-count"><?= $centres ? (int)$centres->num_rows : 0 ?> locations</span>
            </div>

            <div class="staff-centre-grid">
                <?php if($centres && $centres->num_rows): ?>
                    <?php while($c = $centres->fetch_assoc()): ?>
                        <article class="staff-centre-card">
                            <div class="staff-centre-top">
                                <div class="staff-centre-icon">⌖</div>
                                <div>
                                    <h3><?= e($c['centre_name']) ?></h3>
                                    <span><?= e($c['city']) ?></span>
                                </div>
                            </div>

                            <div class="staff-centre-info">
                                <p><?= e($c['address']) ?></p>

                                <div class="staff-centre-meta">
                                    <?php if(trim((string)$c['phone']) !== ''): ?>
                                        <span>Phone: <?= e($c['phone']) ?></span>
                                    <?php endif; ?>
                                    <span>Hours: <?= e($c['opening_hours']) ?></span>
                                    <span>Lat/Lng: <?= e($c['latitude']) ?>, <?= e($c['longitude']) ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="staff-empty-state">
                        <strong>No service centres found.</strong>
                        <span>Add the first service centre using the form on the left.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php render_footer(); ?>
