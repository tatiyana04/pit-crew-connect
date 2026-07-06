<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $user = current_user();

    if ($action === 'reply_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $body = trim($_POST['message'] ?? '');

        if ($bookingId > 0 && $body !== '') {
            $senderName = $user['full_name'] ?? 'PitCrew Staff';
            $senderEmail = $user['email'] ?? '';
            $senderId = (int)$user['id'];
            $role = 'staff';

            $stmt = $conn->prepare("INSERT INTO booking_messages (booking_id, sender_role, sender_user_id, sender_name, sender_email, message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isisss', $bookingId, $role, $senderId, $senderName, $senderEmail, $body);
            $stmt->execute();

            $message = 'Reply sent to the booking conversation.';
        }
    }

    if ($action === 'handle_contact') {
        $contactId = (int)($_POST['contact_id'] ?? 0);

        $stmt = $conn->prepare("UPDATE contact_messages SET status = 'Handled', handled_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $contactId);
        $stmt->execute();

        $message = 'Contact message marked as handled.';
    }
}

$contactMessages = $conn->query("SELECT * FROM contact_messages ORDER BY FIELD(COALESCE(status,'New'),'New','Handled'), created_at DESC LIMIT 30");

$bookingThreads = $conn->query("SELECT b.id, b.booking_code, b.customer_name, b.email, b.vehicle_model, b.service_type, b.status,
        MAX(m.created_at) AS last_message_at,
        COUNT(m.id) AS message_count
    FROM booking_messages m
    JOIN bookings b ON b.id = m.booking_id
    GROUP BY b.id, b.booking_code, b.customer_name, b.email, b.vehicle_model, b.service_type, b.status
    ORDER BY last_message_at DESC
    LIMIT 25");

$contactCount = $contactMessages ? (int)$contactMessages->num_rows : 0;
$threadCount = $bookingThreads ? (int)$bookingThreads->num_rows : 0;

render_header('Messages', 'staff-messages');
?>
<main class="page-wrap staff-page">
    <section class="page-hero compact staff-message-hero">
        <div class="container staff-hero-split">
            <div>
                <span class="crumb">Staff Area / Messages</span>
                <h1>Customer messages</h1>
                <p>View contact enquiries and reply to booking-specific customer conversations from one organized workspace.</p>
            </div>

            <div class="staff-hero-note">
                <span>Response hub</span>
                <strong>Keep customers updated clearly</strong>
                <small>Handle new enquiries first, then reply to booking conversations with progress updates.</small>
            </div>
        </div>
    </section>

    <section class="section container staff-message-page">
        <?php if($message): ?>
            <div class="alert success"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="staff-message-stats">
            <article class="staff-message-stat">
                <span>Contact enquiries</span>
                <strong><?= $contactCount ?></strong>
                <small>Latest customer contact form messages</small>
            </article>
            <article class="staff-message-stat">
                <span>Booking threads</span>
                <strong><?= $threadCount ?></strong>
                <small>Conversations linked with bookings</small>
            </article>
            <article class="staff-message-stat accent">
                <span>Priority</span>
                <strong>New</strong>
                <small>Reply to new or active conversations first</small>
            </article>
        </div>

        <div class="staff-message-layout">
            <section class="staff-message-panel">
                <div class="staff-panel-head">
                    <div>
                        <span class="eyebrow">Inbox</span>
                        <h2>Contact enquiries</h2>
                    </div>
                    <span class="staff-panel-count"><?= $contactCount ?> item<?= $contactCount === 1 ? '' : 's' ?></span>
                </div>

                <?php if(!$contactMessages || $contactMessages->num_rows === 0): ?>
                    <div class="staff-empty-state">
                        <strong>No contact messages yet</strong>
                        <span>New contact form messages will appear here.</span>
                    </div>
                <?php else: ?>
                    <div class="staff-message-list">
                        <?php while($c = $contactMessages->fetch_assoc()): ?>
                            <?php $isHandled = ($c['status'] ?? 'New') === 'Handled'; ?>
                            <article class="staff-message-card <?= $isHandled ? 'handled' : 'new' ?>">
                                <div class="staff-message-card-top">
                                    <div>
                                        <strong><?= e($c['full_name']) ?></strong>
                                        <span><?= e($c['email']) ?><?= $c['phone'] ? ' • ' . e($c['phone']) : '' ?></span>
                                    </div>
                                    <span class="badge"><?= $isHandled ? 'Handled' : 'New' ?></span>
                                </div>

                                <div class="staff-message-body">
                                    <h3><?= e($c['subject']) ?></h3>
                                    <p><?= nl2br(e($c['message'])) ?></p>
                                </div>

                                <div class="staff-message-foot">
                                    <small><?= e($c['created_at']) ?></small>

                                    <?php if(!$isHandled): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="handle_contact">
                                            <input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn tiny secondary" type="submit">Mark handled</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="staff-message-panel">
                <div class="staff-panel-head">
                    <div>
                        <span class="eyebrow">Conversations</span>
                        <h2>Booking replies</h2>
                    </div>
                    <span class="staff-panel-count"><?= $threadCount ?> thread<?= $threadCount === 1 ? '' : 's' ?></span>
                </div>

                <?php if(!$bookingThreads || $bookingThreads->num_rows === 0): ?>
                    <div class="staff-empty-state">
                        <strong>No booking messages yet</strong>
                        <span>Customer replies linked to bookings will appear here.</span>
                    </div>
                <?php else: ?>
                    <div class="staff-message-list">
                        <?php while($b = $bookingThreads->fetch_assoc()): ?>
                            <article class="staff-thread-card">
                                <div class="staff-message-card-top">
                                    <div>
                                        <strong><?= e(booking_code($b)) ?> - <?= e($b['customer_name']) ?></strong>
                                        <span><?= e($b['email']) ?></span>
                                    </div>
                                    <span class="badge"><?= e($b['status']) ?></span>
                                </div>

                                <div class="staff-thread-summary">
                                    <span><?= e($b['vehicle_model']) ?></span>
                                    <span><?= e($b['service_type']) ?></span>
                                    <span><?= (int)$b['message_count'] ?> message<?= (int)$b['message_count'] === 1 ? '' : 's' ?></span>
                                </div>

                                <details class="staff-thread-details">
                                    <summary>
                                        <span>Open conversation</span>
                                        <small>Last update: <?= e($b['last_message_at']) ?></small>
                                    </summary>

                                    <?php
                                        $msgStmt = $conn->prepare("SELECT * FROM booking_messages WHERE booking_id = ? ORDER BY created_at ASC");
                                        $msgStmt->bind_param('i', $b['id']);
                                        $msgStmt->execute();
                                        $msgs = $msgStmt->get_result();
                                    ?>

                                    <div class="thread-box staff-thread-box">
                                        <?php while($m = $msgs->fetch_assoc()): ?>
                                            <div class="thread-message <?= e($m['sender_role']) ?>">
                                                <strong>
                                                    <?= e($m['sender_name']) ?>
                                                    <span><?= e($m['sender_role']) ?></span>
                                                </strong>
                                                <p><?= nl2br(e($m['message'])) ?></p>
                                                <small><?= e($m['created_at']) ?></small>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>

                                    <form method="post" class="reply-form staff-reply-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="reply_booking">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <textarea name="message" rows="3" placeholder="Reply to customer..." required></textarea>
                                        <button class="btn tiny" type="submit">Send Reply</button>
                                    </form>
                                </details>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>
<?php render_footer(); ?>
