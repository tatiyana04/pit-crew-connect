<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = find_user_by_email($email);

    if (
        $user &&
        in_array($user['role'], ['admin', 'staff'], true) &&
        password_verify($password, $user['password_hash'] ?? '')
    ) {
        login_user($user);
        header('Location: staff-dashboard.php');
        exit;
    }

    $error = 'Invalid staff login details.';
}

render_header('Staff Sign In', 'staff-login', ['hide_splash' => true]);
?>

<main class="page-wrap">
<section class="section container auth-wrap">
    <form class="card form-card auth-card" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="session_type" value="staff">

        <span class="eyebrow">Staff workspace</span>
        <h1>Staff sign in</h1>
        <p>Access bookings, employee assignment, customer messages, service centres, and website content.</p>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <label>
            Email
            <input type="email" name="email" required value="staff@pitcrewconnect.com">
        </label>

        <label>
            Password
            <input type="password" name="password" required placeholder="Staff@12345">
        </label>

        <button class="btn" type="submit">Sign In</button>

        <p class="muted-text small">Default staff account: staff@pitcrewconnect.com / Staff@12345</p>
    </form>
</section>
</main>

<?php render_footer(); ?>