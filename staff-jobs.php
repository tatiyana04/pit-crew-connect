<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Team Assigned');
    $allowedStatuses = ['Team Assigned', 'On the Way', 'Arrived', 'Service In Progress', 'Completed', 'Cancelled'];

    if ($bookingId <= 0) {
        $error = 'Invalid booking selected.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = 'Invalid status selected.';
    } else {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $bookingId);
        $stmt->execute();
        $message = 'Job status updated.';
    }
}

$jobs = $conn->query("SELECT b.*,
        sl.latitude AS latest_lat,
        sl.longitude AS latest_lng,
        sl.updated_at AS location_updated_at,
        sl.eta_minutes AS latest_eta
    FROM bookings b
    LEFT JOIN (
        SELECT s1.*
        FROM staff_locations s1
        INNER JOIN (
            SELECT booking_id, MAX(updated_at) AS max_updated
            FROM staff_locations
            GROUP BY booking_id
        ) s2 ON s1.booking_id = s2.booking_id AND s1.updated_at = s2.max_updated
    ) sl ON sl.booking_id = b.id
    WHERE b.status NOT IN ('Completed','Cancelled')
    ORDER BY FIELD(b.status,'On the Way','Team Assigned','Confirmed','Pending','Arrived','Service In Progress','Completed','Cancelled'),
             b.preferred_date,
             b.preferred_time");

render_header('Field Updates', 'staff-jobs');
?>
<main class="page-wrap staff-page">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Staff Area / Field Updates</span>
        <h1>Assigned service jobs</h1>
        <p>Use this page to update job progress and share service team location for customer tracking.</p>
    </div>
</section>

<section class="section container">
    <div class="card staff-main">
        <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <div class="notice" style="margin-bottom: 18px;">
            Browser GPS sharing requires HTTPS in most modern browsers. The location buttons are ready for HTTPS deployment; on the current HTTP lab URL, the page will show a clear security warning instead of requesting GPS permission.
        </div>

        <div class="job-list phase3-job-list">
            <?php if ($jobs->num_rows === 0): ?>
                <div class="notice">No active jobs are available.</div>
            <?php endif; ?>

            <?php while ($j = $jobs->fetch_assoc()): ?>
                <?php
                    $latestEta = $j['latest_eta'] ?: $j['eta_minutes'];
                    $locationText = $j['location_updated_at'] ?: 'Not shared yet';
                    $isMobile = $j['service_mode'] === 'mobile_service';
                    $etaForSharing = $latestEta ?: 30;
                    $empNames = get_booking_employee_names((int)$j['id']);
                ?>
                <article class="job-card phase3-job-card" id="job-<?= (int)$j['id'] ?>">
                    <div class="job-main">
                        <div class="job-title-row">
                            <h3><?= e(booking_code($j)) ?> - <?= e($j['customer_name']) ?></h3>
                            <span class="badge status-<?= e(strtolower(str_replace(' ', '-', $j['status']))) ?>"><?= e($j['status']) ?></span>
                        </div>
                        <p><?= e($j['vehicle_model']) ?> • <?= e($j['service_type']) ?> • <?= e($isMobile ? 'Mobile service' : 'Centre visit') ?></p>
                        <p><strong>Employees:</strong> <?= e($empNames) ?></p>
                        <p><?= e($j['customer_address'] ?: $j['preferred_location']) ?></p>
                        <div class="mini-grid">
                            <div><span>Date</span><strong><?= e($j['preferred_date']) ?></strong></div>
                            <div><span>Time</span><strong><?= e($j['preferred_time']) ?></strong></div>
                            <div><span>ETA</span><strong class="job-latest-eta" data-booking="<?= (int)$j['id'] ?>"><?= e($latestEta ?: 'Not set') ?><?= $latestEta ? ' min' : '' ?></strong></div>
                            <div><span>Location update</span><strong class="job-location-updated" data-booking="<?= (int)$j['id'] ?>"><?= e($locationText) ?></strong></div>
                        </div>
                    </div>

                    <div class="job-control-panel">
                        <form method="post" class="job-actions compact-actions">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int)$j['id'] ?>">
                            <label>Status
                                <select name="status">
                                    <?php foreach (['Team Assigned', 'On the Way', 'Arrived', 'Service In Progress', 'Completed', 'Cancelled'] as $status): ?>
                                        <option value="<?= e($status) ?>" <?= $j['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="btn small" type="submit">Update Status</button>
                        </form>

                        <div class="live-location-panel" data-booking="<?= (int)$j['id'] ?>">
                            <div class="live-location-header">
                                <span class="live-location-pulse" aria-hidden="true"></span>
                                <div>
                                    <strong>Service team location</strong>
                                    <small class="muted-text live-location-last" data-booking="<?= (int)$j['id'] ?>">Last shared: <?= e($locationText) ?></small>
                                </div>
                            </div>
                            <p class="muted-text live-location-status" data-booking="<?= (int)$j['id'] ?>">Click Share Once or Start Live Location. Customer tracking will refresh automatically.</p>
                            <div class="location-actions clean-location-actions">
                                <button type="button" class="btn small start-live-location" data-booking="<?= (int)$j['id'] ?>" data-csrf="<?= e(csrf_token()) ?>" data-eta="<?= e($etaForSharing) ?>">Start Live Location</button>
                                <button type="button" class="btn secondary small stop-live-location" data-booking="<?= (int)$j['id'] ?>" disabled>Stop Sharing</button>
                                <button type="button" class="btn secondary small share-location" data-booking="<?= (int)$j['id'] ?>" data-csrf="<?= e(csrf_token()) ?>" data-eta="<?= e($etaForSharing) ?>">Share Once</button>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    </div>
</section>
</main>
<?php render_footer(); ?>