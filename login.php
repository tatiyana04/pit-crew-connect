<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $passwordInput = $_POST['password'] ?? '';
    $found = find_user_by_email($email);

    if (!$found || empty($found['password_hash']) || !password_verify($passwordInput, $found['password_hash'])) {
        $errors[] = 'Invalid email or password.';
    } elseif ($found['role'] !== 'customer') {
        $errors[] = 'Please use the staff sign-in page for staff accounts.';
    } else {
        login_user($found);
        redirect_after_login($found['role']);
    }
}

render_header('Sign In', 'login');
?>

<main class="auth-page">
<section class="auth-card">
    <span class="eyebrow">Sign in</span>
    <h1>Welcome back</h1>
    <p>Access your bookings, profile, and service tracking in one place.</p>

    <?php foreach ($errors as $err): ?>
        <div class="alert error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>
        <div id="googleSignIn" class="google-box"></div>
        <div class="divider">or</div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="session_type" value="customer">

        <label>
            Email
            <input type="email" name="email" required>
        </label>

        <label>
            Password
            <input type="password" name="password" required>
        </label>

        <button class="btn full-width" type="submit">Sign In</button>
    </form>

    <p class="auth-switch">New customer? <a href="signup.php">Create account</a></p>
    <p class="auth-switch">Staff member? <a href="staff-login.php">Staff sign in</a></p>
</section>
</main>

<?php render_footer(); ?>