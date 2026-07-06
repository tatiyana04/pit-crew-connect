<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
render_header('Car Care Tips', 'tips');

function tips_column_exists($table, $column) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function default_tip_image_for_title($title) {
    $key = strtolower(trim($title));
    $map = [
        'oil change timing' => 'assets/images/tips/oil-change.jpg',
        'tyre safety' => 'assets/images/tips/tyre-safety.jpg',
        'brake warning signs' => 'assets/images/tips/brake-warning.jpg',
        'weak battery signs' => 'assets/images/tips/battery-health.jpg'
    ];
    return $map[$key] ?? '';
}

function default_tip_guidance($title, $category) {
    $key = strtolower(trim($title . ' ' . $category));

    if (strpos($key, 'oil') !== false || strpos($key, 'engine') !== false) {
        return [
            'Check your oil level at least once a month.',
            'Change oil on time based on mileage or service interval.',
            'Book an inspection if the oil looks very dark, smells burnt, or the engine feels rough.'
        ];
    }

    if (strpos($key, 'tyre') !== false || strpos($key, 'tire') !== false || strpos($key, 'wheel') !== false) {
        return [
            'Check tyre pressure before long trips.',
            'Inspect tread depth and sidewall cracks regularly.',
            'Rotate or replace tyres if wear is uneven or grip feels weak.'
        ];
    }

    if (strpos($key, 'brake') !== false) {
        return [
            'Listen for squeaking, grinding, or unusual brake noises.',
            'Check if the vehicle vibrates or pulls to one side when braking.',
            'Book a brake inspection if stopping distance becomes longer.'
        ];
    }

    if (strpos($key, 'battery') !== false || strpos($key, 'charging') !== false) {
        return [
            'Watch for slow starting or dim headlights.',
            'Check battery terminals for corrosion.',
            'Test the battery if the vehicle needs repeated jump-starts.'
        ];
    }

    return [
        'Check this area regularly during normal vehicle use.',
        'Pay attention to unusual sounds, warning lights, smells, leaks, or performance changes.',
        'Book a PitCrew inspection if the issue continues or affects safety.'
    ];
}

function tip_tags($category, $hasImage) {
    $tags = [];
    $category = trim((string)$category);
    if ($category !== '') {
        $tags[] = $category;
    }
    $tags[] = 'Maintenance guide';
    $tags[] = $hasImage ? 'Visual tip' : 'Quick tip';
    return $tags;
}

$tipCards = [];

if (app_table_exists('car_tips')) {
    $hasImageColumn = tips_column_exists('car_tips', 'image_path');
    $selectImage = $hasImageColumn ? ', image_path' : '';
    $res = $conn->query("SELECT icon, title, summary, category{$selectImage} FROM car_tips WHERE is_active = 1 ORDER BY sort_order, title");

    while ($row = $res->fetch_assoc()) {
        $customImage = $hasImageColumn ? trim((string)($row['image_path'] ?? '')) : '';
        $image = $customImage !== '' ? $customImage : default_tip_image_for_title($row['title']);

        $tipCards[] = [
            'icon' => $row['icon'] ?: '🔧',
            'title' => $row['title'],
            'image' => $image,
            'summary' => $row['summary'],
            'guidance' => default_tip_guidance($row['title'], $row['category'] ?? ''),
            'tags' => tip_tags($row['category'] ?? '', $image !== '')
        ];
    }
}

if (!$tipCards) {
    $tipCards = [
        [
            'icon' => '🛢️',
            'title' => 'Oil change timing',
            'image' => 'assets/images/tips/oil-change.jpg',
            'summary' => 'Clean engine oil reduces friction, protects moving parts, and helps the engine run smoothly during daily driving.',
            'guidance' => default_tip_guidance('Oil change timing', 'Engine'),
            'tags' => ['Engine care', 'Daily driving', 'Service reminder']
        ],
        [
            'icon' => '🛞',
            'title' => 'Tyre safety',
            'image' => 'assets/images/tips/tyre-safety.jpg',
            'summary' => 'Good tyres improve braking, road grip, fuel efficiency, and safety, especially during rain or long-distance travel.',
            'guidance' => default_tip_guidance('Tyre safety', 'Tyres'),
            'tags' => ['Road safety', 'Pressure check', 'Wet roads']
        ],
        [
            'icon' => '🛑',
            'title' => 'Brake warning signs',
            'image' => 'assets/images/tips/brake-warning.jpg',
            'summary' => 'Brake issues should be checked quickly because small warning signs can become serious safety problems.',
            'guidance' => default_tip_guidance('Brake warning signs', 'Brakes'),
            'tags' => ['Brake check', 'Quick inspection', 'Safety first']
        ],
        [
            'icon' => '🔋',
            'title' => 'Weak battery signs',
            'image' => 'assets/images/tips/battery-health.jpg',
            'summary' => 'A weak battery can cause starting problems and electrical issues, especially after short trips or long parking periods.',
            'guidance' => default_tip_guidance('Weak battery signs', 'Battery'),
            'tags' => ['Battery check', 'Starting issue', 'Charging system']
        ]
    ];
}
?>
<main class="page-wrap">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Home / Tips</span>
        <h1>Car care tips for safer daily driving.</h1>
        <p>Use the assistant below to get a simple maintenance suggestion based on mileage and driving style.</p>
    </div>
</section>

<section class="section container tips-grid tips-modern-grid">
    <div class="card form-card tips-assistant-card">
        <h2>Car Care Assistant</h2>
        <label>Mileage<input type="number" id="tipMileage" placeholder="85000"></label>
        <label>Driving style<select id="tipDriving"><option>Daily city driving</option><option>Long-distance driving</option><option>Weekend use</option><option>Commercial/fleet use</option></select></label>
        <button class="btn" type="button" id="getTipBtn">Get recommendation</button>
        <div id="tipResult" class="smart-tip">Enter details to see a service suggestion.</div>
    </div>

    <div class="tips-feature-showcase" data-tips-showcase>
        <?php foreach ($tipCards as $index => $card): ?>
            <?php $hasImage = trim((string)($card['image'] ?? '')) !== ''; ?>
            <article class="tips-feature-card <?= $index === 0 ? 'is-active' : '' ?> <?= $hasImage ? 'has-image' : 'no-image' ?>"
                     tabindex="0"
                     role="button"
                     aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                <?php if($hasImage): ?>
                    <div class="tips-feature-image">
                        <img src="<?= e($card['image']) ?>" alt="<?= e($card['title']) ?>">
                    </div>
                <?php endif; ?>
                <div class="tips-feature-shade"></div>
                <div class="tips-feature-content">
                    <span class="tips-feature-icon"><?= e($card['icon']) ?></span>
                    <div class="tips-feature-copy">
                        <h3><?= e($card['title']) ?></h3>
                        <p><?= e($card['summary']) ?></p>
                        <ul class="tips-guide-list">
                            <?php foreach ($card['guidance'] as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tips-feature-tags">
                            <?php foreach ($card['tags'] as $tag): ?>
                                <span><?= e($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
</main>
<?php render_footer(); ?>
