<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
render_header('Packages', 'packages');

$packages = [];
if (app_table_exists('package_catalog')) {
    $res = $conn->query("SELECT * FROM package_catalog WHERE is_active = 1 ORDER BY sort_order, title");
    while ($row = $res->fetch_assoc()) $packages[] = $row;
}
if (!$packages) {
    $packages = [
        ['title'=>'Basic Pit Check','badge'=>'Entry','price_label'=>'Rs. 3,500+','description'=>'For quick safety checks before routine driving.','features_text'=>"Tyre pressure check\nBattery visual check\nLights and fluid review"],
        ['title'=>'Standard Service','badge'=>'Most popular','price_label'=>'Rs. 12,500+','description'=>'A balanced service request for everyday car owners.','features_text'=>"Oil change booking\nBrake inspection\nTyre and battery check"],
        ['title'=>'Full PitCrew Service','badge'=>'Complete','price_label'=>'Rs. 22,000+','description'=>'A full vehicle care request inspired by pit-stop coordination.','features_text'=>"Complete maintenance check\nPriority queue option\nService notes and report"],
        ['title'=>'Emergency Pit Stop','badge'=>'Priority','price_label'=>'Rs. 15,000+','description'=>'For urgent vehicle concerns that need faster review.','features_text'=>"Priority staff review\nMobile service option\nUrgent slot request"]
    ];
}
?>
<main class="page-wrap">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Home / Packages</span>
        <h1>Service packages for different vehicle needs.</h1>
        <p>Compare what each package includes before submitting your booking request.</p>
    </div>
</section>
<section class="section container">
    <div class="pricing-grid">
        <?php foreach ($packages as $p): ?>
        <article class="pricing-card <?= ($p['badge'] ?? '') === 'Most popular' ? 'featured' : '' ?>">
            <span class="pill"><?= e($p['badge'] ?? 'Package') ?></span>
            <h3><?= e($p['title']) ?></h3>
            <div class="price"><?= e($p['price_label'] ?? '') ?></div>
            <p><?= e($p['description']) ?></p>
            <ul>
                <?php foreach (preg_split('/\r\n|\r|\n/', trim($p['features_text'] ?? '')) as $item): ?>
                    <?php if(trim($item) !== ''): ?><li><?= e($item) ?></li><?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <a class="btn small" href="booking.php?package=<?= urlencode($p['title']) ?>">Select package</a>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<section class="section container">
    <div class="section-head"><span class="eyebrow">Compare packages</span><h2>Choose the right level of care.</h2></div>
    <div class="table-wrap">
        <table class="compare-table">
            <thead><tr><th>Feature</th><th>Basic</th><th>Standard</th><th>Full</th><th>Emergency</th></tr></thead>
            <tbody>
                <tr><td>Tyre check</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                <tr><td>Battery check</td><td>✓</td><td>✓</td><td>✓</td><td>Optional</td></tr>
                <tr><td>Oil service booking</td><td>—</td><td>✓</td><td>✓</td><td>Optional</td></tr>
                <tr><td>Brake inspection</td><td>—</td><td>✓</td><td>✓</td><td>✓</td></tr>
                <tr><td>Priority review</td><td>—</td><td>—</td><td>✓</td><td>✓</td></tr>
                <tr><td>Mobile service option</td><td>—</td><td>Optional</td><td>Optional</td><td>✓</td></tr>
            </tbody>
        </table>
    </div>
</section>
</main>
<?php render_footer(); ?>