<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Pending');
    $eta = ($_POST['eta_minutes'] ?? '') !== '' ? (int)$_POST['eta_minutes'] : null;
    $employeeIds = array_map('intval', $_POST['employee_ids'] ?? []);
    $employeeIds = array_values(array_filter(array_unique($employeeIds), fn($id) => $id > 0));

    if ($bookingId > 0) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ?, eta_minutes = ? WHERE id = ?");
        $stmt->bind_param('sii', $status, $eta, $bookingId);
        $stmt->execute();

        if (app_table_exists('booking_employees')) {
            $del = $conn->prepare("DELETE FROM booking_employees WHERE booking_id = ?");
            $del->bind_param('i', $bookingId);
            $del->execute();

            if ($employeeIds) {
                $ins = $conn->prepare("INSERT INTO booking_employees (booking_id, employee_id) VALUES (?, ?)");
                foreach ($employeeIds as $empId) {
                    $ins->bind_param('ii', $bookingId, $empId);
                    $ins->execute();
                }
            }
        }

        $message = 'Booking updated successfully.';
    }
}

$employees = [];
if (app_table_exists('employees')) {
    $res = $conn->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY full_name");
    while ($eRow = $res->fetch_assoc()) $employees[] = $eRow;
}

$statuses = ['Pending','Confirmed','Team Assigned','On the Way','Arrived','Service In Progress','Completed','Cancelled'];
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
$types = '';
if ($statusFilter !== '') { $where[] = 'b.status = ?'; $params[] = $statusFilter; $types .= 's'; }
if ($search !== '') { $where[] = '(b.booking_code LIKE ? OR b.customer_name LIKE ? OR b.email LIKE ? OR b.vehicle_registration LIKE ?)'; $q = '%'.$search.'%'; $params = array_merge($params, [$q,$q,$q,$q]); $types .= 'ssss'; }
$sql = "SELECT b.* FROM bookings b" . ($where ? ' WHERE '.implode(' AND ', $where) : '') . " ORDER BY FIELD(b.urgency_level,'Emergency','Urgent','Normal'), b.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();

render_header('Booking Manager', 'staff-bookings');
?>
<main class="page-wrap staff-page staff-bookings-page">
<section class="page-hero compact">
    <div class="container">
        <span class="crumb">Staff Area / Bookings</span>
        <h1>Booking manager</h1>
        <p>Search, filter, assign one or more employees, update status, and set ETA for customer tracking.</p>
    </div>
</section>

<section class="section container">
    <div class="card staff-main staff-bookings-panel">
        <?php if($message): ?>
            <div class="alert success"><?= e($message) ?></div>
        <?php endif; ?>

        <form class="filter-bar staff-booking-filter" method="get">
            <input name="search" placeholder="Search ID, name, email, registration" value="<?= e($search) ?>">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach($statuses as $s): ?>
                    <option <?= $statusFilter===$s?'selected':'' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn small" type="submit">Filter</button>
        </form>

        <div class="staff-booking-list">
            <?php if ($bookings->num_rows === 0): ?>
                <div class="empty-state">
                    <h3>No bookings found</h3>
                    <p>Try changing the search text or status filter.</p>
                </div>
            <?php endif; ?>

            <?php while($b=$bookings->fetch_assoc()): ?>
                <?php
                    $assigned = array_map(fn($emp) => (int)$emp['id'], get_booking_employees((int)$b['id']));
                    $modeLabel = $b['service_mode']==='mobile_service' ? 'Mobile service' : 'Centre visit';
                    $urgencyClass = 'urgency-' . strtolower((string)$b['urgency_level']);
                ?>
                <article class="staff-booking-card">
                    <div class="staff-booking-info">
                        <div class="staff-booking-topline">
                            <span class="booking-id-chip"><?= e(booking_code($b)) ?></span>
                            <span class="badge <?= e($urgencyClass) ?>"><?= e($b['urgency_level']) ?></span>
                        </div>

                        <h3><?= e($b['customer_name']) ?></h3>
                        <p class="staff-booking-email"><?= e($b['email']) ?></p>

                        <div class="staff-booking-details">
                            <div>
                                <span>Vehicle</span>
                                <strong><?= e($b['vehicle_model']) ?></strong>
                                <small><?= e($b['vehicle_registration']) ?></small>
                            </div>
                            <div>
                                <span>Service</span>
                                <strong><?= e($b['service_type']) ?></strong>
                                <small><?= e($b['package_type']) ?></small>
                            </div>
                            <div>
                                <span>Mode</span>
                                <strong><?= e($modeLabel) ?></strong>
                                <small><?= e($b['status']) ?></small>
                            </div>
                        </div>
                    </div>

                    <form method="post" class="staff-booking-controls assignment-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">

                        <div class="staff-control-grid">
                            <label>
                                <span>Status</span>
                                <select name="status" class="status-select">
                                    <?php foreach($statuses as $s): ?>
                                        <option <?= $b['status']===$s?'selected':'' ?>><?= e($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>ETA minutes</span>
                                <input type="number" name="eta_minutes" min="0" placeholder="ETA" value="<?= e($b['eta_minutes']) ?>">
                            </label>
                        </div>

                        <div class="employee-picker" data-booking-id="<?= (int)$b['id'] ?>">
                            <span class="staff-control-label">Employees</span>

                            <div class="employee-chip-list" aria-live="polite"></div>

                            <div class="employee-search-wrap">
                                <input type="text" class="employee-search" placeholder="Type employee name to assign" autocomplete="off">
                                <div class="employee-suggestions" role="listbox"></div>
                            </div>

                            <select name="employee_ids[]" multiple class="employee-hidden-select" aria-hidden="true" tabindex="-1">
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?= (int)$emp['id'] ?>"
                                            <?= in_array((int)$emp['id'], $assigned, true)?'selected':'' ?>
                                            data-job-title="<?= e($emp['job_title']) ?>">
                                        <?= e($emp['full_name']) ?> - <?= e($emp['job_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small class="employee-picker-help">New bookings start blank. Type a name, choose the employee, then click Save.</small>
                        </div>

                        <div class="staff-booking-actions">
                            <button class="btn tiny" type="submit">Save changes</button>
                        </div>
                    </form>
                </article>
            <?php endwhile; ?>
        </div>
    </div>
</section>
</main>
<?php render_footer(); ?>
