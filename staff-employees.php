<?php
define('PITCREW_SESSION_CONTEXT', 'staff');

require_once __DIR__ . '/layout.php';
require_staff();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? 'Service Technician');
        $notes = trim($_POST['notes'] ?? '');

        if ($fullName === '') {
            $error = 'Employee name is required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO employees (full_name, email, phone, job_title, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $fullName, $email, $phone, $jobTitle, $notes);
            $stmt->execute();
            $message = 'Employee added successfully.';
        }
    }

    if ($action === 'toggle_employee') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1);
        $stmt = $conn->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $isActive, $employeeId);
        $stmt->execute();
        $message = 'Employee status updated.';
    }
}

$employees = $conn->query("SELECT * FROM employees ORDER BY is_active DESC, full_name");
render_header('Employees', 'staff-employees');
?>

<main class="page-wrap staff-page staff-directory-page">
    <section class="page-hero compact staff-directory-hero">
        <div class="container">
            <span class="crumb">Staff Area / Employees</span>
            <h1>Employee manager</h1>
            <p>Add employees, keep contact details organized, and make active team members available for booking assignments.</p>
        </div>
    </section>

    <section class="section container staff-directory-layout">
        <form class="card form-card staff-directory-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_employee">

            <div class="staff-form-kicker">New team member</div>
            <h2>Add employee</h2>
            <p class="staff-form-note">Create employee records for service technicians, advisors, mobile service staff, and booking support roles.</p>

            <?php if($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

            <label>Full name
                <input name="full_name" required placeholder="Employee name">
            </label>

            <label>Email
                <input type="email" name="email" placeholder="employee@example.com">
            </label>

            <label>Phone
                <input name="phone" placeholder="0771234567">
            </label>

            <label>Role / title
                <input name="job_title" value="Service Technician">
            </label>

            <label>Notes
                <textarea name="notes" rows="4" placeholder="Special skills, availability, or team notes."></textarea>
            </label>

            <button class="btn" type="submit">Add Employee</button>
        </form>

        <div class="staff-directory-panel">
            <div class="staff-directory-head">
                <div>
                    <span class="eyebrow">Team directory</span>
                    <h2>Employees</h2>
                    <p>Active employees can be assigned to bookings from the booking manager.</p>
                </div>
                <span class="staff-directory-count"><?= $employees ? (int)$employees->num_rows : 0 ?> records</span>
            </div>

            <div class="staff-employee-grid">
                <?php if($employees && $employees->num_rows): ?>
                    <?php while($eRow = $employees->fetch_assoc()): ?>
                        <article class="staff-employee-card <?= $eRow['is_active'] ? 'is-active' : 'is-inactive' ?>">
                            <div class="staff-employee-main">
                                <div class="staff-employee-avatar">
                                    <?= e(strtoupper(substr(trim($eRow['full_name'] ?? 'E'), 0, 1))) ?>
                                </div>

                                <div class="staff-employee-details">
                                    <div class="staff-employee-title-row">
                                        <h3><?= e($eRow['full_name']) ?></h3>
                                        <span class="badge <?= $eRow['is_active'] ? 'success' : '' ?>">
                                            <?= $eRow['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <p class="staff-employee-role"><?= e($eRow['job_title']) ?></p>

                                    <?php if(trim((string)$eRow['notes']) !== ''): ?>
                                        <p class="staff-employee-notes"><?= e($eRow['notes']) ?></p>
                                    <?php else: ?>
                                        <p class="staff-employee-notes muted">No team notes added yet.</p>
                                    <?php endif; ?>

                                    <div class="staff-employee-contact">
                                        <?php if(trim((string)$eRow['email']) !== ''): ?>
                                            <span><?= e($eRow['email']) ?></span>
                                        <?php endif; ?>
                                        <?php if(trim((string)$eRow['phone']) !== ''): ?>
                                            <span><?= e($eRow['phone']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <form method="post" class="staff-card-action">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_employee">
                                <input type="hidden" name="employee_id" value="<?= (int)$eRow['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $eRow['is_active'] ? 0 : 1 ?>">
                                <button class="btn tiny secondary" type="submit">
                                    <?= $eRow['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="staff-empty-state">
                        <strong>No employees found.</strong>
                        <span>Add the first employee using the form on the left.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php render_footer(); ?>
