<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
render_header('Services', 'services');

$services = [];
if (app_table_exists('service_catalog')) {
    $res = $conn->query("SELECT * FROM service_catalog WHERE is_active = 1 ORDER BY sort_order, title");
    while ($row = $res->fetch_assoc()) $services[] = $row;
}
if (!$services) {
    $services = [
        ['title'=>'Oil Change','price_label'=>'Rs. 6,500+','description'=>'Fresh engine oil support for smoother daily driving.','duration'=>'30-45 min','best_for'=>'Daily drivers','includes_text'=>"Oil level review\nFilter check\nBasic fluid top-up review"],
        ['title'=>'Tyre Check','price_label'=>'Rs. 3,500+','description'=>'Tyre pressure, tread depth, and visible damage checks.','duration'=>'20-30 min','best_for'=>'Long trips','includes_text'=>"Pressure check\nTread check\nRotation advice"],
        ['title'=>'Brake Inspection','price_label'=>'Rs. 5,500+','description'=>'Brake safety review for noise, vibration, or reduced stopping response.','duration'=>'30-45 min','best_for'=>'Safety concerns','includes_text'=>"Brake pad inspection\nFluid check\nSafety recommendation"]
    ];
}
?>
<main class="page-wrap">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Home / Services</span>
        <h1>Vehicle services for daily drivers and urgent needs.</h1>
        <p>Choose a service, compare the best package, and submit a request that the PitCrew team can review and manage.</p>
    </div>
</section>
<section class="section container">
    <div class="service-grid">
        <?php foreach ($services as $s): ?>
        <article class="service-card">
            <div class="service-card-top">
                <h3><?= e($s['title']) ?></h3>
                <span><?= e($s['price_label'] ?? '') ?></span>
            </div>
            <p><?= e($s['description']) ?></p>
            <div class="mini-meta"><span>Time: <?= e($s['duration'] ?? 'By schedule') ?></span><span>Best for: <?= e($s['best_for'] ?? 'Vehicle care') ?></span></div>
            <ul>
                <?php foreach (preg_split('/\r\n|\r|\n/', trim($s['includes_text'] ?? '')) as $item): ?>
                    <?php if(trim($item) !== ''): ?><li><?= e($item) ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <a class="btn small" href="booking.php?service=<?= urlencode($s['title']) ?>">Book this service</a>
        </article>
        <?php endforeach; ?>
    </div>
</section>
</main>
<?php render_footer(); ?>