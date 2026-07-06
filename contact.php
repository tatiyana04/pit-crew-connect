<?php
define('PITCREW_SESSION_CONTEXT', 'customer');

require_once __DIR__ . '/layout.php';
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Service enquiry');
    $message = trim($_POST['message'] ?? '');
    if ($name && $email && $message) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $email, $phone, $subject, $message);
        $stmt->execute();
        $success = true;
    } else {
        $error = 'Please complete your name, email, and message.';
    }
}
render_header('Contact', 'contact');
?>
<main class="page-wrap">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Home / Contact</span>
        <h1>Send a message to the PitCrew team.</h1>
        <p>Use the form below for service questions, package enquiries, mobile service requests, or booking support. Staff will receive it in the staff workspace.</p>
    </div>
</section>
<section class="section container">
    <form class="card form-card narrow-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if($success): ?><div class="alert success">Your message has been sent to the PitCrew staff team.</div><?php endif; ?>
        <?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <label>Name<input name="full_name" required></label>
        <label>Email<input type="email" name="email" required></label>
        <label>Phone<input name="phone"></label>
        <label>Subject<input name="subject" value="Service enquiry"></label>
        <label>Message<textarea name="message" rows="6" required placeholder="Write your enquiry here."></textarea></label>
        <button class="btn" type="submit">Send Message</button>
    </form>
</section>
</main>
<?php render_footer(); ?>