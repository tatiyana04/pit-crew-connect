<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$counts = ['total'=>0,'pending'=>0,'urgent'=>0,'mobile'=>0,'employees'=>0,'messages'=>0];
$r = $conn->query("SELECT COUNT(*) total, SUM(status='Pending') pending, SUM(urgency_level IN ('Urgent','Emergency')) urgent, SUM(service_mode='mobile_service') mobile FROM bookings");
if ($row = $r->fetch_assoc()) $counts = array_merge($counts, array_map('intval', $row));
if (app_table_exists('employees')) {
    $r = $conn->query("SELECT COUNT(*) c FROM employees WHERE is_active = 1");
    $counts['employees'] = (int)$r->fetch_assoc()['c'];
}
if (app_table_exists('booking_messages')) {
    $r = $conn->query("SELECT COUNT(*) c FROM booking_messages");
    $counts['messages'] += (int)$r->fetch_assoc()['c'];
}
if (app_table_exists('contact_messages')) {
    $r = $conn->query("SELECT COUNT(*) c FROM contact_messages WHERE COALESCE(status,'New') <> 'Handled'");
    $counts['messages'] += (int)$r->fetch_assoc()['c'];
}

$recent = $conn->query("SELECT b.* FROM bookings b ORDER BY b.created_at DESC LIMIT 8");
render_header('Staff Dashboard', 'staff-dashboard');
?>
<main class="page-wrap staff-page">
<section class="page-hero compact staff-dashboard-hero">
    <div class="container">
        <div class="staff-dashboard-hero-copy">
            <span class="crumb">Staff Area</span>
            <h1>PitCrew staff dashboard</h1>
            <p>Review requests, assign employees, update customer-facing progress, reply to messages, and manage service content.</p>
        </div>
        <div class="staff-hero-actions">
            <a class="btn" href="staff-bookings.php">Manage bookings</a>
            <a class="btn secondary" href="staff-messages.php">Open messages</a>
        </div>
    </div>
</section>

<section class="section container staff-overview-section">
    <div class="staff-stat-grid">
        <div class="stat-card staff-kpi-card is-info">
            <div class="staff-kpi-top">
                <span>Total bookings</span>
                <span class="staff-kpi-icon">📋</span>
            </div>
            <strong><?= $counts['total'] ?></strong>
            <p class="staff-kpi-note">All customer service requests</p>
        </div>

        <div class="stat-card staff-kpi-card">
            <div class="staff-kpi-top">
                <span>Pending review</span>
                <span class="staff-kpi-icon">⏱️</span>
            </div>
            <strong><?= $counts['pending'] ?></strong>
            <p class="staff-kpi-note">Waiting for confirmation</p>
        </div>

        <div class="stat-card staff-kpi-card is-warning">
            <div class="staff-kpi-top">
                <span>Urgent queue</span>
                <span class="staff-kpi-icon">⚠️</span>
            </div>
            <strong><?= $counts['urgent'] ?></strong>
            <p class="staff-kpi-note">Needs faster attention</p>
        </div>

        <div class="stat-card staff-kpi-card is-success">
            <div class="staff-kpi-top">
                <span>Active employees</span>
                <span class="staff-kpi-icon">👥</span>
            </div>
            <strong><?= $counts['employees'] ?></strong>
            <p class="staff-kpi-note">Available staff records</p>
        </div>

        <div class="stat-card staff-kpi-card is-info">
            <div class="staff-kpi-top">
                <span>Open messages</span>
                <span class="staff-kpi-icon">✉️</span>
            </div>
            <strong><?= $counts['messages'] ?></strong>
            <p class="staff-kpi-note">Customer communication</p>
        </div>
    </div>
</section>

<section class="section container staff-dashboard-section">
    <div class="staff-dashboard-layout">
        <div class="card staff-dashboard-panel">
            <div class="staff-dashboard-panel-head">
                <div class="section-head left">
                    <span class="eyebrow">Recent requests</span>
                    <h2>Latest bookings</h2>
                    <p>Quickly review new bookings and open the booking manager when a request needs assignment or status updates.</p>
                </div>
                <a class="btn secondary" href="staff-bookings.php">View all</a>
            </div>

            <div class="table-wrap staff-table-wrap">
                <table class="staff-dashboard-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Employees</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent && $recent->num_rows): ?>
                            <?php while($b=$recent->fetch_assoc()): ?>
                                <?php
                                    $urgency = (string)($b['urgency_level'] ?? '');
                                    $urgencyHot = in_array($urgency, ['Urgent', 'Emergency'], true);
                                    $statusClass = 'staff-status-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$b['status']));
                                ?>
                                <tr>
                                    <td><span class="staff-booking-code"><?= e(booking_code($b)) ?></span></td>
                                    <td><?= e($b['customer_name']) ?></td>
                                    <td><?= e($b['service_type']) ?></td>
                                    <td><span class="staff-urgency-badge <?= $urgencyHot ? 'is-hot' : '' ?>"><?= e($urgency) ?></span></td>
                                    <td><span class="staff-status-badge <?= e($statusClass) ?>"><?= e($b['status']) ?></span></td>
                                    <td><span class="staff-employee-cell"><?= e(get_booking_employee_names((int)$b['id']) ?: 'Not assigned') ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td class="staff-empty-row" colspan="6">No recent bookings found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="staff-dashboard-actions">
                <a class="btn" href="staff-bookings.php">Open booking manager</a>
                <a class="btn secondary" href="staff-employees.php">Manage employees</a>
                <a class="btn secondary" href="staff-messages.php">Open messages</a>
                <a class="btn secondary" href="deployment-status.php" target="_blank" rel="noopener">Deployment status</a>
            </div>
        </div>

        <aside class="staff-side-stack">
            <div class="staff-side-card dark">
                <h3>Today’s focus</h3>
                <p>Use these quick numbers to decide what should be handled first.</p>
                <div class="staff-focus-list">
                    <div class="staff-focus-item"><span>Pending review</span><strong><?= $counts['pending'] ?></strong></div>
                    <div class="staff-focus-item"><span>Urgent requests</span><strong><?= $counts['urgent'] ?></strong></div>
                    <div class="staff-focus-item"><span>Mobile services</span><strong><?= $counts['mobile'] ?></strong></div>
                </div>
            </div>

            <div class="staff-side-card">
                <h3>Quick actions</h3>
                <p>Jump into the most common staff tasks.</p>
                <div class="staff-shortcut-list">
                    <a href="staff-bookings.php">Assign booking jobs</a>
                    <a href="staff-employees.php">Update employees</a>
                    <a href="staff-content.php">Edit service content</a>
                    <a href="staff-centres.php">Manage service centres</a>
                </div>
            </div>
        </aside>
    </div>
</section>
</main>
<?php render_footer(); ?>
