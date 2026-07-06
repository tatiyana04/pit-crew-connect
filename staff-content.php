<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$message = '';
$error = '';

function bool_from_post($name) {
    return isset($_POST[$name]) ? 1 : 0;
}

function staff_content_tip_image_column_exists() {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'car_tips' AND column_name = 'image_path'");
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'] > 0;
}

function staff_content_ensure_tip_image_column() {
    global $conn;
    if (app_table_exists('car_tips') && !staff_content_tip_image_column_exists()) {
        $conn->query("ALTER TABLE car_tips ADD COLUMN image_path VARCHAR(255) NULL AFTER category");
    }
}

function staff_content_tip_upload_dir() {
    return __DIR__ . '/assets/images/tips/uploads';
}

function staff_content_tip_public_upload_path($filename) {
    return 'assets/images/tips/uploads/' . $filename;
}

function staff_content_uploaded_tip_image($fieldName, &$error) {
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $error = 'Tip image upload failed. Please choose a valid image file.';
        return false;
    }

    if (($_FILES[$fieldName]['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Tip image is too large. Please upload an image below 5MB.';
        return false;
    }

    $tmpName = $_FILES[$fieldName]['tmp_name'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        $error = 'Please upload a JPG, PNG, or WebP image for the tip.';
        return false;
    }

    $uploadDir = staff_content_tip_upload_dir();
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeName = 'tip_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($tmpName, $target)) {
        $error = 'Could not save the uploaded tip image.';
        return false;
    }

    @chmod($target, 0664);
    return staff_content_tip_public_upload_path($safeName);
}

function staff_content_delete_tip_image_file($imagePath) {
    $imagePath = trim((string)$imagePath);
    if ($imagePath === '') return;

    $allowedPrefix = 'assets/images/tips/uploads/';
    if (strpos($imagePath, $allowedPrefix) !== 0) {
        return;
    }

    $fullPath = __DIR__ . '/' . $imagePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function staff_content_tip_image_url($imagePath) {
    $imagePath = trim((string)$imagePath);
    return $imagePath !== '' ? $imagePath : '';
}

staff_content_ensure_tip_image_column();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $type = $_POST['type'] ?? '';

    if ($type === 'service') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $bestFor = trim($_POST['best_for'] ?? '');
        $price = trim($_POST['price_label'] ?? '');
        $includes = trim($_POST['includes_text'] ?? '');
        $active = bool_from_post('is_active');

        if ($title && $description) {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE service_catalog SET title=?, description=?, duration=?, best_for=?, price_label=?, includes_text=?, is_active=? WHERE id=?");
                $stmt->bind_param('ssssssii', $title, $description, $duration, $bestFor, $price, $includes, $active, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO service_catalog (title, description, duration, best_for, price_label, includes_text, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssssi', $title, $description, $duration, $bestFor, $price, $includes, $active);
            }
            $stmt->execute();
            $message = 'Service content saved.';
        } else {
            $error = 'Service title and description are required.';
        }
    }

    if ($type === 'package') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $badge = trim($_POST['badge'] ?? '');
        $price = trim($_POST['price_label'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features_text'] ?? '');
        $active = bool_from_post('is_active');

        if ($title && $description) {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE package_catalog SET title=?, badge=?, price_label=?, description=?, features_text=?, is_active=? WHERE id=?");
                $stmt->bind_param('sssssii', $title, $badge, $price, $description, $features, $active, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO package_catalog (title, badge, price_label, description, features_text, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssssi', $title, $badge, $price, $description, $features, $active);
            }
            $stmt->execute();
            $message = 'Package content saved.';
        } else {
            $error = 'Package title and description are required.';
        }
    }

    if ($type === 'package_delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM package_catalog WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $message = 'Package deleted successfully.';
        } else {
            $error = 'Invalid package selected for deletion.';
        }
    }

    if ($type === 'tip') {
        $id = (int)($_POST['id'] ?? 0);
        $icon = trim($_POST['icon'] ?? '🔧');
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $active = bool_from_post('is_active');
        $removeImage = isset($_POST['remove_image']) ? 1 : 0;

        $currentImage = '';
        if ($id > 0) {
            $imgStmt = $conn->prepare("SELECT image_path FROM car_tips WHERE id = ? LIMIT 1");
            $imgStmt->bind_param('i', $id);
            $imgStmt->execute();
            $imgRow = $imgStmt->get_result()->fetch_assoc();
            $currentImage = $imgRow['image_path'] ?? '';
        }

        $uploadedImage = staff_content_uploaded_tip_image('tip_image', $error);
        if ($uploadedImage === false) {
            // Error message already set by uploader.
        } elseif ($title && $summary) {
            $imagePath = $currentImage;

            if ($removeImage) {
                staff_content_delete_tip_image_file($currentImage);
                $imagePath = null;
            }

            if ($uploadedImage !== null) {
                staff_content_delete_tip_image_file($currentImage);
                $imagePath = $uploadedImage;
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE car_tips SET icon=?, title=?, summary=?, category=?, image_path=?, is_active=? WHERE id=?");
                $stmt->bind_param('sssssii', $icon, $title, $summary, $category, $imagePath, $active, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO car_tips (icon, title, summary, category, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssssi', $icon, $title, $summary, $category, $imagePath, $active);
            }
            $stmt->execute();
            $message = 'Tip content saved.';
        } else {
            $error = 'Tip title and summary are required.';
        }
    }

    if ($type === 'tip_delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $imgStmt = $conn->prepare("SELECT image_path FROM car_tips WHERE id = ? LIMIT 1");
            $imgStmt->bind_param('i', $id);
            $imgStmt->execute();
            $imgRow = $imgStmt->get_result()->fetch_assoc();

            $stmt = $conn->prepare("DELETE FROM car_tips WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($imgRow) {
                staff_content_delete_tip_image_file($imgRow['image_path'] ?? '');
            }

            $message = 'Tip deleted successfully.';
        } else {
            $error = 'Invalid tip selected for deletion.';
        }
    }
}

$services = $conn->query("SELECT * FROM service_catalog ORDER BY sort_order, title");
$packages = $conn->query("SELECT * FROM package_catalog ORDER BY sort_order, title");
$tips = $conn->query("SELECT * FROM car_tips ORDER BY sort_order, title");

render_header('Services & Content', 'staff-content');
?>

<main class="page-wrap staff-page staff-content-page">
<section class="page-hero compact staff-content-hero">
    <div class="container">
        <span class="crumb">Staff Area / Services & Content</span>
        <h1>Manage services, packages, and tips</h1>
        <p>Update the customer-facing service catalogue, package options, and car care tips from one organised workspace.</p>
    </div>
</section>

<section class="section container staff-content-admin">
    <?php if($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <div class="staff-content-summary">
        <article class="staff-content-summary-card">
            <span class="summary-icon">🛠️</span>
            <div>
                <small>Service catalogue</small>
                <strong><?= (int)$services->num_rows ?></strong>
            </div>
        </article>
        <article class="staff-content-summary-card">
            <span class="summary-icon">📦</span>
            <div>
                <small>Package options</small>
                <strong><?= (int)$packages->num_rows ?></strong>
            </div>
        </article>
        <article class="staff-content-summary-card">
            <span class="summary-icon">💡</span>
            <div>
                <small>Car care tips</small>
                <strong><?= (int)$tips->num_rows ?></strong>
            </div>
        </article>
    </div>

    <div class="staff-content-form-grid">
        <form class="card form-card staff-content-form-card" method="post">
            <div class="form-card-head">
                <span class="form-icon">🛠️</span>
                <div>
                    <h2>Add service</h2>
                    <p>Create a public service option for customers to select during booking.</p>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="type" value="service">
            <label>Service name<input name="title" required placeholder="Oil Change"></label>
            <div class="form-grid">
                <label>Price label<input name="price_label" placeholder="Rs. 6,500+"></label>
                <label>Duration<input name="duration" placeholder="30-45 min"></label>
            </div>
            <label>Best for<input name="best_for" placeholder="Daily drivers"></label>
            <label>Description<textarea name="description" required rows="3" placeholder="Short customer-facing explanation."></textarea></label>
            <label>Included items<textarea name="includes_text" rows="4" placeholder="One item per line"></textarea></label>
            <label class="check-row"><input type="checkbox" name="is_active" checked> Active</label>
            <button class="btn small" type="submit">Add Service</button>
        </form>

        <form class="card form-card staff-content-form-card" method="post">
            <div class="form-card-head">
                <span class="form-icon">📦</span>
                <div>
                    <h2>Add package</h2>
                    <p>Build package choices such as Basic, Standard, Full, or Emergency options.</p>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="type" value="package">
            <label>Package name<input name="title" required placeholder="Standard Service"></label>
            <div class="form-grid">
                <label>Badge<input name="badge" placeholder="Most popular"></label>
                <label>Price label<input name="price_label" placeholder="Rs. 12,500+"></label>
            </div>
            <label>Description<textarea name="description" required rows="3" placeholder="Short package description."></textarea></label>
            <label>Features<textarea name="features_text" rows="4" placeholder="One feature per line"></textarea></label>
            <label class="check-row"><input type="checkbox" name="is_active" checked> Active</label>
            <button class="btn small" type="submit">Add Package</button>
        </form>

        <form class="card form-card staff-content-form-card" method="post" enctype="multipart/form-data">
            <div class="form-card-head">
                <span class="form-icon">💡</span>
                <div>
                    <h2>Add tip</h2>
                    <p>Publish quick car care guidance for customers on the Tips page.</p>
                </div>
            </div>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="type" value="tip">
            <div class="form-grid compact">
                <label>Icon<input name="icon" value="🔧"></label>
                <label>Category<input name="category" placeholder="Tyres, Brakes, Battery"></label>
            </div>
            <label>Title<input name="title" required placeholder="Tyre safety"></label>
            <label>Summary<textarea name="summary" required rows="4" placeholder="Short customer-facing tip."></textarea></label>
            <label>Tip image <small>(optional JPG, PNG, or WebP)</small><input type="file" name="tip_image" accept="image/jpeg,image/png,image/webp"></label>
            <div class="upload-note">If no image is uploaded, the tip will still appear on the Tips page using a clean no-image card.</div>
            <label class="check-row"><input type="checkbox" name="is_active" checked> Active</label>
            <button class="btn small" type="submit">Add Tip</button>
        </form>
    </div>
</section>

<section class="section container content-section-panel">
    <div class="content-section-head">
        <div>
            <span class="eyebrow">Service catalogue</span>
            <h2>Current services</h2>
            <p>Services shown across the public Services and Booking pages.</p>
        </div>
    </div>

    <div class="content-record-grid service-record-grid">
        <?php if ($services->num_rows === 0): ?>
            <div class="empty-state-card">No services added yet.</div>
        <?php endif; ?>
        <?php while($s=$services->fetch_assoc()): ?>
            <article class="content-record-card service-record-card">
                <div class="content-record-top">
                    <span class="record-icon">🛠️</span>
                    <span class="badge <?= $s['is_active'] ? 'success' : 'muted' ?>"><?= $s['is_active'] ? 'Active' : 'Hidden' ?></span>
                </div>
                <h3><?= e($s['title']) ?></h3>
                <p><?= e($s['description']) ?></p>
                <div class="record-meta">
                    <?php if(trim($s['price_label'] ?? '') !== ''): ?><span><?= e($s['price_label']) ?></span><?php endif; ?>
                    <?php if(trim($s['duration'] ?? '') !== ''): ?><span><?= e($s['duration']) ?></span><?php endif; ?>
                    <?php if(trim($s['best_for'] ?? '') !== ''): ?><span><?= e($s['best_for']) ?></span><?php endif; ?>
                </div>
                <?php if(trim($s['includes_text'] ?? '') !== ''): ?>
                    <div class="record-note"><?= nl2br(e($s['includes_text'])) ?></div>
                <?php endif; ?>

                <details class="content-edit-details">
                    <summary>Edit service</summary>
                    <form method="post" class="mini-edit-form content-mini-edit">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="type" value="service">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <label>Service name<input name="title" value="<?= e($s['title']) ?>"></label>
                        <div class="form-grid">
                            <label>Price label<input name="price_label" value="<?= e($s['price_label']) ?>"></label>
                            <label>Duration<input name="duration" value="<?= e($s['duration']) ?>"></label>
                        </div>
                        <label>Best for<input name="best_for" value="<?= e($s['best_for']) ?>"></label>
                        <label>Description<textarea name="description" rows="3"><?= e($s['description']) ?></textarea></label>
                        <label>Included items<textarea name="includes_text" rows="4"><?= e($s['includes_text']) ?></textarea></label>
                        <label class="check-row"><input type="checkbox" name="is_active" <?= $s['is_active']?'checked':'' ?>> Active</label>
                        <button class="btn tiny" type="submit">Save service</button>
                    </form>
                </details>
            </article>
        <?php endwhile; ?>
    </div>
</section>

<section class="section container content-section-panel">
    <div class="content-section-head">
        <div>
            <span class="eyebrow">Packages</span>
            <h2>Current packages</h2>
            <p>Package options customers compare before choosing their service level.</p>
        </div>
    </div>

    <div class="content-record-grid package-record-grid">
        <?php if ($packages->num_rows === 0): ?>
            <div class="empty-state-card">No packages added yet.</div>
        <?php endif; ?>
        <?php while($p=$packages->fetch_assoc()): ?>
            <article class="content-record-card package-record-card">
                <div class="content-record-top">
                    <span class="record-icon">📦</span>
                    <div class="content-record-actions">
                        <span class="badge <?= $p['is_active'] ? 'success' : 'muted' ?>"><?= $p['is_active'] ? 'Active' : 'Hidden' ?></span>
                        <form method="post" class="content-delete-form" onsubmit="return confirm('Delete this package permanently?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="type" value="package_delete">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn tiny danger" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
                <div class="package-heading-line">
                    <h3><?= e($p['title']) ?></h3>
                    <?php if(trim($p['badge'] ?? '') !== ''): ?><span class="package-badge"><?= e($p['badge']) ?></span><?php endif; ?>
                </div>
                <p><?= e($p['description']) ?></p>
                <div class="record-meta">
                    <?php if(trim($p['price_label'] ?? '') !== ''): ?><span><?= e($p['price_label']) ?></span><?php endif; ?>
                </div>
                <?php if(trim($p['features_text'] ?? '') !== ''): ?>
                    <div class="record-note"><?= nl2br(e($p['features_text'])) ?></div>
                <?php endif; ?>

                <details class="content-edit-details">
                    <summary>Edit package</summary>
                    <form method="post" class="mini-edit-form content-mini-edit">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="type" value="package">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <label>Package name<input name="title" value="<?= e($p['title']) ?>"></label>
                        <div class="form-grid">
                            <label>Badge<input name="badge" value="<?= e($p['badge']) ?>"></label>
                            <label>Price label<input name="price_label" value="<?= e($p['price_label']) ?>"></label>
                        </div>
                        <label>Description<textarea name="description" rows="3"><?= e($p['description']) ?></textarea></label>
                        <label>Features<textarea name="features_text" rows="4"><?= e($p['features_text']) ?></textarea></label>
                        <label class="check-row"><input type="checkbox" name="is_active" <?= $p['is_active']?'checked':'' ?>> Active</label>
                        <button class="btn tiny" type="submit">Save package</button>
                    </form>
                </details>
            </article>
        <?php endwhile; ?>
    </div>
</section>

<section class="section container content-section-panel">
    <div class="content-section-head">
        <div>
            <span class="eyebrow">Tips</span>
            <h2>Current car care tips</h2>
            <p>Short educational content shown to customers in the car care tips area. Tips can be published with or without an image.</p>
        </div>
    </div>

    <div class="content-record-grid tips-record-grid">
        <?php if ($tips->num_rows === 0): ?>
            <div class="empty-state-card">No tips added yet.</div>
        <?php endif; ?>
        <?php while($t=$tips->fetch_assoc()): ?>
            <?php $tipImage = staff_content_tip_image_url($t['image_path'] ?? ''); ?>
            <article class="content-record-card tip-record-card <?= $tipImage ? 'has-tip-image' : 'no-tip-image' ?>">
                <?php if($tipImage): ?>
                    <div class="tip-admin-preview">
                        <img src="<?= e($tipImage) ?>" alt="<?= e($t['title']) ?>">
                    </div>
                <?php endif; ?>

                <div class="content-record-top">
                    <span class="record-icon"><?= e($t['icon']) ?></span>
                    <div class="content-record-actions">
                        <span class="badge <?= $t['is_active'] ? 'success' : 'muted' ?>"><?= $t['is_active'] ? 'Active' : 'Hidden' ?></span>
                        <form method="post" class="content-delete-form" onsubmit="return confirm('Delete this tip permanently?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="type" value="tip_delete">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <button class="btn tiny danger" type="submit">Delete</button>
                        </form>
                    </div>
                </div>

                <h3><?= e($t['title']) ?></h3>
                <p><?= e($t['summary']) ?></p>

                <div class="record-meta">
                    <?php if(trim($t['category'] ?? '') !== ''): ?><span><?= e($t['category']) ?></span><?php endif; ?>
                    <span><?= $tipImage ? 'Image attached' : 'No image' ?></span>
                </div>

                <details class="content-edit-details">
                    <summary>Edit tip</summary>
                    <form method="post" enctype="multipart/form-data" class="mini-edit-form content-mini-edit">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="type" value="tip">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <div class="form-grid compact">
                            <label>Icon<input name="icon" value="<?= e($t['icon']) ?>"></label>
                            <label>Category<input name="category" value="<?= e($t['category']) ?>"></label>
                        </div>
                        <label>Title<input name="title" value="<?= e($t['title']) ?>"></label>
                        <label>Summary<textarea name="summary" rows="4"><?= e($t['summary']) ?></textarea></label>
                        <label>Replace image <small>(optional JPG, PNG, or WebP)</small><input type="file" name="tip_image" accept="image/jpeg,image/png,image/webp"></label>
                        <?php if($tipImage): ?>
                            <label class="check-row"><input type="checkbox" name="remove_image"> Remove current image</label>
                        <?php endif; ?>
                        <label class="check-row"><input type="checkbox" name="is_active" <?= $t['is_active']?'checked':'' ?>> Active</label>
                        <button class="btn tiny" type="submit">Save tip</button>
                    </form>
                </details>
            </article>
        <?php endwhile; ?>
    </div>
</section>
</main>
<?php render_footer(); ?>
