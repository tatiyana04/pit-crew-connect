<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    if ($full_name === '' || $email === '' || strlen($passwordInput) < 8) {
        $errors[] = 'Please enter your name, email, and a password with at least 8 characters.';
    } elseif (find_user_by_email($email)) {
        $errors[] = 'An account already exists with this email address.';
    } else {
        $hash = password_hash($passwordInput, PASSWORD_DEFAULT);
        $role = 'customer';
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $full_name, $email, $phone, $hash, $role);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $profile = $conn->prepare("INSERT INTO customer_profiles (user_id) VALUES (?)");
        $profile->bind_param('i', $userId);
        $profile->execute();
        login_user(['id' => $userId, 'role' => 'customer']);
        header('Location: customer-dashboard.php');
        exit;
    }
}
render_header('Create Account', 'login');
?>
<main class="auth-page">
<section class="auth-card">
    <span class="eyebrow">Customer account</span>
    <h1>Create your PitCrew account</h1>
    <p>Save your profile, view booking history, and track service progress faster.</p>
    <?php foreach ($errors as $err): ?><div class="alert error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>
        <div id="googleSignIn" class="google-box"></div>
        <div class="divider">or create with email</div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label>Full name<input name="full_name" required></label>
        <label>Email<input type="email" name="email" required></label>
        <label>Phone<input name="phone"></label>
        <label>Password<input type="password" name="password" minlength="8" required></label>
        <button class="btn full-width" type="submit">Create Account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
</section>
</main>
<?php render_footer(); ?>
