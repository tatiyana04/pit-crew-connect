<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
render_header('Home', 'home');
?>
<main>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">Vehicle service booking made simple</span>
            <h1>Book your next vehicle service with pit-stop speed.</h1>
            <p>PitCrew Connect helps everyday drivers compare packages, request service centre visits or mobile service, and track each booking from request to completion.</p>
            <div class="hero-actions">
                <a class="btn" href="booking.php">Book a Service</a>
                <a class="btn secondary" href="track.php">Track My Booking</a>
            </div>
        </div>
        <div class="hero-panel">
            <div class="queue-card">
                <div class="queue-head"><span>Today’s service flow</span><strong>Open</strong></div>
                <div class="queue-row"><span>Oil change</span><b>30-45 min</b></div>
                <div class="queue-row"><span>Brake inspection</span><b>45 min</b></div>
                <div class="queue-row"><span>Battery check</span><b>20 min</b></div>
                <div class="queue-row"><span>Mobile service request</span><b>ETA shown after assignment</b></div>
            </div>
        </div>
    </div>
</section>

<section class="section container">
    <div class="section-head">
        <span class="eyebrow">Why choose PitCrew</span>
        <h2>A clearer way to manage vehicle care.</h2>
        <p>Designed for customers who want fast requests, transparent packages, and simple service progress updates.</p>
    </div>
    <div class="feature-grid four">
        <article class="feature-card"><span>⚡</span><h3>Fast booking</h3><p>Submit your vehicle details, preferred time, and service package in minutes.</p></article>
        <article class="feature-card"><span>📍</span><h3>Nearest centre</h3><p>Use your location to find a suitable PitCrew centre or request mobile service.</p></article>
        <article class="feature-card"><span>🧾</span><h3>Clear packages</h3><p>Compare Basic, Standard, Full, and Emergency options before booking.</p></article>
        <article class="feature-card"><span>🚗</span><h3>Status tracking</h3><p>Track confirmation, staff assignment, journey, service progress, and completion.</p></article>
    </div>
</section>

<section class="section soft">
    <div class="container split">
        <div>
            <span class="eyebrow">How it works</span>
            <h2>From booking to service completion.</h2>
        </div>
        <div class="steps">
            <div><strong>1</strong><span>Choose service and package</span></div>
            <div><strong>2</strong><span>Select service centre or mobile service</span></div>
            <div><strong>3</strong><span>Submit customer and vehicle details</span></div>
            <div><strong>4</strong><span>Receive booking ID and track progress</span></div>
        </div>
    </div>
</section>

<section class="section container">
    <div class="section-head">
        <span class="eyebrow">Popular services</span>
        <h2>Everyday maintenance and urgent checks.</h2>
    </div>
    <div class="service-strip">
        <a href="booking.php?service=Oil%20Change">Oil Change</a>
        <a href="booking.php?service=Tyre%20Check">Tyre Check</a>
        <a href="booking.php?service=Brake%20Inspection">Brake Inspection</a>
        <a href="booking.php?service=Battery%20Check">Battery Check</a>
        <a href="booking.php?service=General%20Maintenance">General Maintenance</a>
        <a href="booking.php?service=Emergency%20Vehicle%20Check">Emergency Vehicle Check</a>
    </div>
</section>

<section class="cta container">
    <div>
        <h2>Ready to book your next pit stop?</h2>
        <p>Create an account to save your profile, view booking history, and track service progress faster.</p>
    </div>
    <div class="cta-actions">
        <a class="btn dark" href="signup.php">Create Account</a>
        <a class="btn secondary" href="booking.php">Book Now</a>
    </div>
</section>
</main>
<?php render_footer(); ?>
