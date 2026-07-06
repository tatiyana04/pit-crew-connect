<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
require_login();
$user = current_user();
$success = false;
$stmt = $conn->prepare("SELECT * FROM customer_profiles WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
if (!$profile) { $conn->query("INSERT INTO customer_profiles (user_id) VALUES (".(int)$user['id'].")"); $profile = []; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $vehicle = trim($_POST['default_vehicle_model']);
    $reg = trim($_POST['default_vehicle_registration']);
    $address = trim($_POST['address']);
    $upUser = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $upUser->bind_param('ssi', $name, $phone, $user['id']);
    $upUser->execute();
    $upProfile = $conn->prepare("UPDATE customer_profiles SET default_vehicle_model = ?, default_vehicle_registration = ?, address = ? WHERE user_id = ?");
    $upProfile->bind_param('sssi', $vehicle, $reg, $address, $user['id']);
    $upProfile->execute();
    $success = true;
    $user = current_user();
    $profile = ['default_vehicle_model'=>$vehicle, 'default_vehicle_registration'=>$reg, 'address'=>$address];
}
render_header('Profile', 'profile');
?>
<main class="page-wrap">
<section class="page-hero compact"><div class="container"><span class="crumb">My Account / Profile</span><h1>Customer profile</h1><p>Save details that can be reused when booking future vehicle services.</p></div></section>
<section class="section container"><form class="card form-card narrow" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><?php if ($success): ?><div class="alert success">Profile updated successfully.</div><?php endif; ?><label>Full name<input name="full_name" value="<?= e($user['full_name']) ?>" required></label><label>Phone<input name="phone" value="<?= e($user['phone']) ?>"></label><label>Default vehicle model<input name="default_vehicle_model" value="<?= e($profile['default_vehicle_model'] ?? '') ?>"></label><label>Default registration number<input name="default_vehicle_registration" value="<?= e($profile['default_vehicle_registration'] ?? '') ?>"></label><label>Address<textarea name="address" rows="4"><?= e($profile['address'] ?? '') ?></textarea></label><button class="btn" type="submit">Save Profile</button></form></section>
</main>
<?php render_footer(); ?>
