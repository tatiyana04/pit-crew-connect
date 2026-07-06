<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';

$bookingInput = trim($_GET['booking'] ?? $_POST['booking'] ?? '');
$emailInput = trim($_GET['email'] ?? $_POST['email'] ?? '');
$booking = null;
$staffLocation = null;
$assignedEmployees = [];
$threadMessages = [];
$messageSent = false;
$messageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_booking_message') {
    verify_csrf();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $bookingInput = trim($_POST['booking'] ?? '');
    $emailInput = trim($_POST['email'] ?? '');
    $body = trim($_POST['message'] ?? '');

    if ($bookingId > 0 && $body !== '') {
        $check = $conn->prepare("SELECT id, customer_name, email FROM bookings WHERE id = ? AND email = ? LIMIT 1");
        $check->bind_param('is', $bookingId, $emailInput);
        $check->execute();
        $found = $check->get_result()->fetch_assoc();

        if ($found) {
            $user = current_user();
            $senderName = $user ? $user['full_name'] : $found['customer_name'];
            $senderEmail = $user ? $user['email'] : $found['email'];
            $senderUserId = $user ? (int)$user['id'] : null;
            $senderRole = 'customer';
            $stmt = $conn->prepare("INSERT INTO booking_messages (booking_id, sender_role, sender_user_id, sender_name, sender_email, message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isisss', $bookingId, $senderRole, $senderUserId, $senderName, $senderEmail, $body);
            $stmt->execute();
            $messageSent = true;
        } else {
            $messageError = 'This message could not be linked to your booking.';
        }
    } else {
        $messageError = 'Please type a message before sending.';
    }
}

if ($bookingInput && $emailInput) {
    $numeric = preg_replace('/[^0-9]/', '', $bookingInput);

    if ($numeric !== '') {
        $stmt = $conn->prepare("SELECT b.*,
                sc.centre_name,
                sc.address AS centre_address,
                sc.city AS centre_city,
                sc.latitude AS centre_lat,
                sc.longitude AS centre_lng
            FROM bookings b
            LEFT JOIN service_centres sc ON b.service_centre_id = sc.id
            WHERE (b.booking_code = ? OR b.id = ?) AND b.email = ?
            LIMIT 1");
        $stmt->bind_param('sis', $bookingInput, $numeric, $emailInput);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if ($booking) {
            $assignedEmployees = get_booking_employees((int)$booking['id']);

            $locStmt = $conn->prepare("SELECT latitude, longitude, eta_minutes, updated_at
                FROM staff_locations
                WHERE booking_id = ?
                ORDER BY updated_at DESC
                LIMIT 1");
            $locStmt->bind_param('i', $booking['id']);
            $locStmt->execute();
            $staffLocation = $locStmt->get_result()->fetch_assoc();

            if (app_table_exists('booking_messages')) {
                $msgStmt = $conn->prepare("SELECT * FROM booking_messages WHERE booking_id = ? ORDER BY created_at ASC");
                $msgStmt->bind_param('i', $booking['id']);
                $msgStmt->execute();
                $msgs = $msgStmt->get_result();
                while($m = $msgs->fetch_assoc()) $threadMessages[] = $m;
            }
        }
    }
}

$stages = [
    'Pending' => 'Booking received',
    'Confirmed' => 'Booking confirmed',
    'Team Assigned' => 'Team assigned',
    'On the Way' => 'Team on the way',
    'Arrived' => 'Team arrived',
    'Service In Progress' => 'Service in progress',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled'
];

$statusOrder = array_keys($stages);
$currentIndex = $booking ? array_search($booking['status'], $statusOrder, true) : -1;
$currentIndex = $currentIndex === false ? -1 : $currentIndex;

render_header('Track Booking', 'track');
?>

<main class="page-wrap">
    <section class="page-hero compact">
        <div class="container">
            <span class="crumb">Home / Track Booking</span>
            <h1>Track your service booking.</h1>
            <p>Enter your booking ID and email to view progress, assigned employees, location updates, ETA, and messages.</p>
        </div>
    </section>

    <section class="section container track-grid">
        <form class="card form-card" method="get" action="track.php">
            <h2>Find booking</h2>
            <label>Booking ID<input name="booking" placeholder="PC-12" value="<?= e($bookingInput) ?>" required></label>
            <label>Email address<input type="email" name="email" placeholder="you@example.com" value="<?= e($emailInput) ?>" required></label>
            <button class="btn" type="submit">Track Booking</button>
        </form>

        <div class="card">
            <?php if ($booking): ?>
                <h2>Booking status</h2>
                <div class="summary-list">
                    <div><span>Booking ID</span><strong><?= e(booking_code($booking)) ?></strong></div>
                    <div><span>Customer</span><strong><?= e($booking['customer_name']) ?></strong></div>
                    <div><span>Vehicle</span><strong><?= e($booking['vehicle_model']) ?></strong></div>
                    <div><span>Service</span><strong><?= e($booking['service_type']) ?></strong></div>
                    <div><span>Package</span><strong><?= e($booking['package_type']) ?></strong></div>
                    <div><span>Status</span><strong class="badge status-<?= e(strtolower(str_replace(' ', '-', $booking['status']))) ?>"><?= e($booking['status']) ?></strong></div>
                    <div><span>Assigned employees</span><strong><?= e($assignedEmployees ? implode(', ', array_map(fn($emp) => $emp['full_name'], $assignedEmployees)) : 'Waiting for assignment') ?></strong></div>
                    <?php if (!empty($booking['eta_minutes'])): ?><div><span>ETA</span><strong><?= (int)$booking['eta_minutes'] ?> minutes</strong></div><?php endif; ?>
                </div>

                <div class="timeline" id="statusTimeline">
                    <?php $i = 0; foreach ($stages as $key => $label): ?>
                        <?php $done = $currentIndex >= 0 && $i <= $currentIndex; ?>
                        <div class="timeline-step <?= $done ? 'done' : '' ?>">
                            <span><?= $done ? '✓' : ($i + 1) ?></span>
                            <p><?= e($label) ?></p>
                        </div>
                    <?php $i++; endforeach; ?>
                </div>
            <?php elseif ($bookingInput || $emailInput): ?>
                <h2>No booking found</h2>
                <p>Please check the booking ID and email address.</p>
            <?php else: ?>
                <h2>Tracking details</h2>
                <p>After submitting a booking, you will receive a booking ID such as PC-12. Use it here with your email address.</p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($booking): ?>
        <section class="section container">
            <div class="card map-card wide">
                <div class="section-head left">
                    <span class="eyebrow">Location and ETA</span>
                    <h2><?= $booking['service_mode'] === 'mobile_service' ? 'Mobile service progress' : 'Selected service centre' ?></h2>
                </div>

                <?php if ($booking['service_mode'] === 'mobile_service'): ?>
                    <p><?= $staffLocation ? 'The latest service team location has been updated.' : 'The team location will appear after staff start the journey.' ?></p>
                <?php else: ?>
                    <p>Your selected centre: <strong><?= e($booking['centre_name'] ?: $booking['preferred_location']) ?></strong></p>
                <?php endif; ?>

                <div class="live-tracking-panel">
                    <div><span>Live status</span><strong id="liveTrackStatus"><?= e($booking['status']) ?></strong></div>
                    <div><span>Latest ETA</span><strong id="liveTrackEta"><?= $booking['eta_minutes'] ? (int)$booking['eta_minutes'] . ' min' : 'Waiting for staff update' ?></strong></div>
                    <div><span>Last location update</span><strong id="liveTrackUpdated"><?= e($staffLocation['updated_at'] ?? 'Not shared yet') ?></strong></div>
                    <div><span>Team coordinates</span><strong id="liveTrackCoords"><?= $staffLocation ? e(number_format((float)$staffLocation['latitude'], 5) . ', ' . number_format((float)$staffLocation['longitude'], 5)) : 'Waiting for location' ?></strong></div>
                </div>

                <div id="trackMap" class="map-placeholder large">Map preview / live team marker appears here when a Google Maps key is configured. Tracking details still update without the map.</div>
            </div>
        </section>

        <section class="section container">
            <div class="card">
                <div class="section-head left"><span class="eyebrow">Booking messages</span><h2>Message the assigned PitCrew team</h2></div>
                <p>Use this conversation for extra details about your booking, arrival instructions, or questions for the assigned employees.</p>
                <?php if($messageSent): ?><div class="alert success">Your message has been sent to the staff team.</div><?php endif; ?>
                <?php if($messageError): ?><div class="alert error"><?= e($messageError) ?></div><?php endif; ?>

                <div class="thread-box">
                    <?php if(!$threadMessages): ?><div class="notice">No messages yet. Send the first message below.</div><?php endif; ?>
                    <?php foreach($threadMessages as $m): ?>
                        <div class="thread-message <?= e($m['sender_role']) ?>">
                            <strong><?= e($m['sender_name']) ?> <span><?= e($m['sender_role']) ?></span></strong>
                            <p><?= nl2br(e($m['message'])) ?></p>
                            <small><?= e($m['created_at']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" class="reply-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="send_booking_message">
                    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                    <input type="hidden" name="booking" value="<?= e(booking_code($booking)) ?>">
                    <input type="hidden" name="email" value="<?= e($booking['email']) ?>">
                    <textarea name="message" rows="4" placeholder="Type a message for the assigned PitCrew team..." required></textarea>
                    <button class="btn" type="submit">Send Message</button>
                </form>
            </div>
        </section>

        <script>
            window.PITCREW_TRACK = <?= json_encode([
                'booking' => $booking,
                'staffLocation' => $staffLocation,
                'email' => $emailInput,
                'bookingInput' => $bookingInput
            ]) ?>;
        </script>
    <?php endif; ?>
</main>

<?php render_footer(); ?>