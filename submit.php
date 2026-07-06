<?php
define('PITCREW_SESSION_CONTEXT', 'customer');
require_once __DIR__ . '/layout.php';

verify_csrf();
$user = current_user();

function clean($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: booking.php');
    exit;
}

$customer_name = clean('customer_name');
$email = clean('email');
$phone = clean('phone');
$vehicle_model = clean('vehicle_model');
$vehicle_registration = clean('vehicle_registration');
$mileage = clean('mileage') !== '' ? (int)clean('mileage') : null;
$fuel_type = clean('fuel_type');
$service_type = clean('service_type');
$package_type = clean('package_type');
$urgency_level = clean('urgency_level', 'Normal');
$service_mode = clean('service_mode', 'service_centre');
$service_centre_id = clean('service_centre_id') !== '' ? (int)clean('service_centre_id') : null;
$customer_address = clean('customer_address');
$customer_lat = clean('customer_lat') !== '' ? (float)clean('customer_lat') : null;
$customer_lng = clean('customer_lng') !== '' ? (float)clean('customer_lng') : null;
$preferred_date = clean('preferred_date');
$preferred_time = clean('preferred_time');
$pickup_required = clean('pickup_required', 'No');
$notes = clean('notes');
$status = 'Pending';
$user_id = $user ? (int)$user['id'] : null;

$baseCosts = [
    'Basic Pit Check' => 3500,
    'Standard Service' => 12500,
    'Full PitCrew Service' => 22000,
    'Emergency Pit Stop' => 15000,
    'Fleet Care Plan' => 30000
];

$estimated_cost = $baseCosts[$package_type] ?? 12500;

if ($urgency_level === 'Urgent') {
    $estimated_cost += 2500;
}

if ($urgency_level === 'Emergency') {
    $estimated_cost += 5000;
}

if ($service_mode === 'mobile_service') {
    $estimated_cost += 3500;
}

if ($pickup_required === 'Yes') {
    $estimated_cost += 2000;
}

$preferred_location = '';

if ($service_centre_id) {
    $stmtCentre = $conn->prepare("SELECT centre_name, city FROM service_centres WHERE id = ? LIMIT 1");
    $stmtCentre->bind_param('i', $service_centre_id);
    $stmtCentre->execute();
    $centre = $stmtCentre->get_result()->fetch_assoc();

    if ($centre) {
        $preferred_location = $centre['centre_name'] . ' - ' . $centre['city'];
    }
}

if ($service_mode === 'mobile_service') {
    $preferred_location = 'Mobile service request';
}

if ($customer_name === '' || $email === '' || $vehicle_model === '' || $service_type === '' || $preferred_date === '' || $preferred_time === '') {
    render_header('Booking Error', 'booking');

    echo '<main class="page-wrap">
        <section class="container section">
            <div class="card">
                <h1>Booking incomplete</h1>
                <p>Please complete the required fields and try again.</p>
                <a class="btn" href="booking.php">Back to booking</a>
            </div>
        </section>
    </main>';

    render_footer();
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO bookings (
        booking_code,
        user_id,
        customer_name,
        email,
        phone,
        vehicle_model,
        vehicle_registration,
        mileage,
        fuel_type,
        service_type,
        package_type,
        urgency_level,
        service_mode,
        customer_address,
        customer_lat,
        customer_lng,
        service_centre_id,
        preferred_location,
        preferred_date,
        preferred_time,
        pickup_required,
        notes,
        status,
        estimated_cost
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $booking_code = 'TMP-' . bin2hex(random_bytes(4));

    $stmt->bind_param(
        'sisssssissssssddissssssd',
        $booking_code,
        $user_id,
        $customer_name,
        $email,
        $phone,
        $vehicle_model,
        $vehicle_registration,
        $mileage,
        $fuel_type,
        $service_type,
        $package_type,
        $urgency_level,
        $service_mode,
        $customer_address,
        $customer_lat,
        $customer_lng,
        $service_centre_id,
        $preferred_location,
        $preferred_date,
        $preferred_time,
        $pickup_required,
        $notes,
        $status,
        $estimated_cost
    );

    $stmt->execute();

    $newId = $stmt->insert_id;
    $booking_code = 'PC-' . $newId;

    $up = $conn->prepare("UPDATE bookings SET booking_code = ? WHERE id = ?");
    $up->bind_param('si', $booking_code, $newId);
    $up->execute();

    render_header('Booking Submitted', 'booking');
    ?>

    <main class="page-wrap">
        <section class="container section success-grid">
            <div class="card success-card">
                <span class="success-icon">✓</span>
                <h1>Booking request received</h1>
                <p>Your PitCrew booking has been saved successfully. Keep your booking ID to track progress.</p>

                <div class="booking-code"><?= e($booking_code) ?></div>

                <div class="summary-list">
                    <div><span>Status</span><strong><?= e($status) ?></strong></div>
                    <div><span>Customer</span><strong><?= e($customer_name) ?></strong></div>
                    <div><span>Vehicle</span><strong><?= e($vehicle_model) ?></strong></div>
                    <div><span>Service</span><strong><?= e($service_type) ?></strong></div>
                    <div><span>Package</span><strong><?= e($package_type) ?></strong></div>
                    <div><span>Estimated cost</span><strong>Rs. <?= number_format($estimated_cost) ?>+</strong></div>
                </div>

                <div class="button-row">
                    <a class="btn" href="track.php?booking=<?= urlencode($booking_code) ?>&email=<?= urlencode($email) ?>">Track this booking</a>
                    <a class="btn secondary" href="booking.php">Make another booking</a>

                    <?php if ($user): ?>
                        <a class="btn secondary" href="my-bookings.php">My bookings</a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="card">
                <h2>What happens next?</h2>
                <ol class="next-list">
                    <li>Staff review your booking request.</li>
                    <li>A team member confirms the service time or assigns mobile service.</li>
                    <li>Your tracking page updates as the booking progresses.</li>
                </ol>
            </aside>
        </section>
    </main>

    <?php
    render_footer();
} catch (Exception $e) {
    error_log('Booking insert failed: ' . $e->getMessage());

    render_header('Booking Error', 'booking');

    echo '<main class="page-wrap">
        <section class="container section">
            <div class="card">
                <h1>Booking could not be saved</h1>
                <p>Please check the details and try again. If the problem continues, contact PitCrew support.</p>
                <a class="btn" href="booking.php">Back to booking</a>
            </div>
        </section>
    </main>';

    render_footer();
}
?>