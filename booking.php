<?php
define('PITCREW_SESSION_CONTEXT', 'customer');
require_once __DIR__ . '/layout.php';

$user = current_user();
$profile = null;

if ($user) {
    $stmt = $conn->prepare("SELECT * FROM customer_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
}

$centres = [];
$res = $conn->query("SELECT * FROM service_centres WHERE is_active = 1 ORDER BY city, centre_name");
while ($row = $res->fetch_assoc()) {
    $centres[] = $row;
}

$serviceOptions = [
    'Oil Change',
    'Tyre Check',
    'Brake Inspection',
    'Battery Check',
    'General Maintenance',
    'Pre-Trip Safety Check',
    'Emergency Vehicle Check',
    'Fleet Maintenance'
];

if (app_table_exists('service_catalog')) {
    $serviceOptions = [];
    $res = $conn->query("SELECT title FROM service_catalog WHERE is_active = 1 ORDER BY sort_order, title");

    while ($row = $res->fetch_assoc()) {
        $serviceOptions[] = $row['title'];
    }
}

$packageOptions = [
    'Basic Pit Check',
    'Standard Service',
    'Full PitCrew Service',
    'Emergency Pit Stop',
    'Fleet Care Plan'
];

if (app_table_exists('package_catalog')) {
    $packageOptions = [];
    $res = $conn->query("SELECT title FROM package_catalog WHERE is_active = 1 ORDER BY sort_order, title");

    while ($row = $res->fetch_assoc()) {
        $packageOptions[] = $row['title'];
    }
}

$prefService = $_GET['service'] ?? '';
$prefPackage = $_GET['package'] ?? '';

render_header('Book Service', 'booking');
?>

<main class="page-wrap">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Home / Book Service</span>
        <h1>Book a service centre visit or request mobile service.</h1>
        <p>Logged-in customers can save profile details and view booking history. Guest bookings are also accepted.</p>
    </div>
</section>

<section class="section container two-col-form">
    <form class="card form-card" method="post" action="submit.php" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="customer_lat" id="customer_lat">
        <input type="hidden" name="customer_lng" id="customer_lng">

        <h2>Customer details</h2>

        <?php if (!$user): ?>
            <div class="notice">Create an account or sign in to save your profile and view all bookings in one place. You can still continue as a guest.</div>
        <?php endif; ?>

        <div class="form-grid">
            <label>Full name
                <input name="customer_name" required value="<?= e($user['full_name'] ?? '') ?>">
            </label>

            <label>Email
                <input type="email" name="email" required value="<?= e($user['email'] ?? '') ?>">
            </label>

            <label>Phone
                <input name="phone" value="<?= e($user['phone'] ?? '') ?>">
            </label>
        </div>

        <h2>Vehicle details</h2>

        <div class="form-grid">
            <label>Vehicle model
                <input name="vehicle_model" required value="<?= e($profile['default_vehicle_model'] ?? '') ?>" placeholder="Toyota Aqua, Honda Civic">
            </label>

            <label>Registration number
                <input name="vehicle_registration" value="<?= e($profile['default_vehicle_registration'] ?? '') ?>" placeholder="CAB-1234">
            </label>

            <label>Mileage (km)
                <input type="number" min="0" name="mileage" id="mileage" placeholder="85000">
            </label>

            <label>Fuel type
                <select name="fuel_type">
                    <option>Petrol</option>
                    <option>Diesel</option>
                    <option>Hybrid</option>
                    <option>Electric</option>
                </select>
            </label>
        </div>

        <h2>Service request</h2>

        <div class="form-grid">
            <label>Service type
                <select name="service_type" id="service_type" required>
                    <?php foreach ($serviceOptions as $service): ?>
                        <option <?= $prefService === $service ? 'selected' : '' ?>>
                            <?= e($service) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Package
                <select name="package_type" id="package_type" required>
                    <?php foreach ($packageOptions as $package): ?>
                        <option <?= $prefPackage === $package ? 'selected' : '' ?>>
                            <?= e($package) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Urgency
                <select name="urgency_level" id="urgency_level">
                    <option>Normal</option>
                    <option>Urgent</option>
                    <option>Emergency</option>
                </select>
            </label>

            <label>Preferred date
                <input type="date" name="preferred_date" required>
            </label>

            <label>Preferred time
                <input type="time" name="preferred_time" required>
            </label>

            <label>Service mode
                <select name="service_mode" id="service_mode">
                    <option value="service_centre">Visit a PitCrew service centre</option>
                    <option value="mobile_service">Request mobile service to my location</option>
                </select>
            </label>
        </div>

        <div class="form-grid full">
            <label>Service centre
                <select name="service_centre_id" id="service_centre_id">
                    <?php foreach ($centres as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" data-lat="<?= e($c['latitude']) ?>" data-lng="<?= e($c['longitude']) ?>">
                            <?= e($c['centre_name']) ?> - <?= e($c['city']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Address for mobile service / pickup
                <input
                    type="text"
                    name="customer_address"
                    id="customer_address"
                    class="address-autocomplete"
                    placeholder="Start typing your pickup or mobile service address"
                    autocomplete="off"
                    value="<?= e($profile['address'] ?? '') ?>"
                >
            </label>

            <label>Pickup and drop-off required?
                <select name="pickup_required" id="pickup_required">
                    <option>No</option>
                    <option>Yes</option>
                </select>
            </label>

            <label>Notes / issue description
                <textarea name="notes" rows="4" placeholder="Describe symptoms, warning lights, noises, or special requests."></textarea>
            </label>
        </div>

        <button class="btn" type="submit">Submit Booking Request</button>
    </form>

    <aside class="booking-side">
        <div class="card summary-card">
            <h3>Booking summary</h3>
            <div class="summary-row"><span>Estimated cost</span><strong id="estimateCost">Rs. 12,500+</strong></div>
            <div class="summary-row"><span>Suggested package</span><strong id="packageSuggestion">Standard Service</strong></div>
            <div class="summary-row"><span>Estimated handling</span><strong id="etaPreview">Same-day review</strong></div>
            <p id="smartTip" class="smart-tip">Enter mileage to receive a service recommendation.</p>
        </div>

        <div class="card map-card">
            <h3>Nearest service centre</h3>
            <p>Allow location access to estimate the nearest PitCrew centre. You can still manually select a centre.</p>
            <button type="button" class="btn secondary full-width" id="useLocationBtn">Use my location</button>
            <div id="nearestCentre" class="nearest-box">Location not selected yet.</div>
            <div id="locationMessage" class="muted small"></div>
            <div id="bookingMap" class="map-placeholder">Map preview will appear here when a map key is configured. The centre list still works without it.</div>
        </div>
    </aside>
</section>

<script>
window.PITCREW_CENTRES = <?= json_encode($centres) ?>;
</script>
</main>

<?php render_footer(); ?>