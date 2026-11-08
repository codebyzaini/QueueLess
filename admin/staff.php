<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    exit('Database connection failed.');
}

$pageTitle = 'Manage Staff';
$activeNav = 'staff';

$error = '';
$setupLink = '';
$successMessage = '';

$name = '';
$email = '';
$selectedService = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $selectedService = trim($_POST['service_id'] ?? '');

        if ($name === '' || strlen($name) > 100) {
            $error = 'Enter a valid staff name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
            $error = 'Enter a valid email address.';
        } elseif ($selectedService !== '' && !ctype_digit($selectedService)) {
            $error = 'Select a valid service.';
        } else {
            try {
                $pdo->beginTransaction();

                $checkEmail = $pdo->prepare(
                    'SELECT id FROM users WHERE email = ? LIMIT 1'
                );
                $checkEmail->execute([$email]);

                if ($checkEmail->fetch()) {
                    throw new RuntimeException('This email address is already registered.');
                }

                $serviceId = $selectedService === ''
                    ? null
                    : (int) $selectedService;

                if ($serviceId !== null) {
                    $serviceQuery = $pdo->prepare(
                        'SELECT id, staff_id FROM services WHERE id = ? FOR UPDATE'
                    );
                    $serviceQuery->execute([$serviceId]);
                    $service = $serviceQuery->fetch();

                    if (!$service) {
                        throw new RuntimeException('Selected service does not exist.');
                    }

                    if ($service['staff_id'] !== null) {
                        throw new RuntimeException('This service already has an assigned staff member.');
                    }
                }

                // Generate an unusable random initial password.
                // The staff member will choose their own password using the setup link.
                $temporaryPassword = bin2hex(random_bytes(32));
                $passwordHash = password_hash(
                    $temporaryPassword,
                    PASSWORD_DEFAULT
                );

                $insertUser = $pdo->prepare(
                    "INSERT INTO users (name, email, password_hash, role)
                     VALUES (?, ?, ?, 'staff')"
                );

                $insertUser->execute([
                    $name,
                    $email,
                    $passwordHash
                ]);

                $staffId = (int) $pdo->lastInsertId();

                if ($serviceId !== null) {
                    $assignService = $pdo->prepare(
                        'UPDATE services SET staff_id = ? WHERE id = ? AND staff_id IS NULL'
                    );
                    $assignService->execute([$staffId, $serviceId]);

                    if ($assignService->rowCount() !== 1) {
                        throw new RuntimeException('Unable to assign this service.');
                    }
                }

                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                $insertToken = $pdo->prepare(
                    'INSERT INTO staff_setup_tokens
                     (user_id, token_hash, expires_at, created_by)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), ?)'
                );

                $admin = current_user();

                $insertToken->execute([
                    $staffId,
                    $tokenHash,
                    (int) $admin['id']
                ]);

                $pdo->commit();

                $setupLink = '/queueless/staff/setup.php?token=' .
                    urlencode($rawToken);

                $successMessage = 'Staff account created successfully. Copy the setup link below and share it securely with the staff member. The link expires in 24 hours.';

                $name = '';
                $email = '';
                $selectedService = '';

            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $exception->getMessage();

            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Staff creation error: ' . $exception->getMessage());
                $error = 'Unable to create the staff account. Please try again.';
            }
        }
    }
}

$services = $pdo->query(
    "SELECT id, name, staff_id
     FROM services
     ORDER BY name ASC"
)->fetchAll();

$staffMembers = $pdo->query(
    "SELECT u.id, u.name, u.email, u.created_at,
            s.name AS service_name
     FROM users u
     LEFT JOIN services s ON s.staff_id = u.id
     WHERE u.role = 'staff'
     ORDER BY u.created_at DESC"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-wrapper">

    <section class="dashboard-header">
        <div>
            <span class="hero-badge-pill">ADMINISTRATION</span>
            <h1 class="dashboard-title">Staff Management</h1>
            <p class="dashboard-subtitle">
                Create staff accounts and assign campus service counters.
            </p>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success">
            <?= e($successMessage) ?>
        </div>

        <section class="dashboard-section">
            <h2>Staff Password Setup Link</h2>
            <p>Copy this link and share it securely with the staff member.</p>

            <div class="form-group">
                <input
                    type="text"
                    class="form-control"
                    value="<?= e('http://' . $_SERVER['HTTP_HOST'] . $setupLink) ?>"
                    readonly
                    onclick="this.select()"
                >
            </div>

            <button
                type="button"
                class="btn btn-primary"
                onclick="navigator.clipboard.writeText(this.dataset.link).then(() => this.textContent = 'Copied!')"
                data-link="<?= e('http://' . $_SERVER['HTTP_HOST'] . $setupLink) ?>"
            >
                Copy Setup Link
            </button>
        </section>
    <?php endif; ?>

    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <h2>Create Staff Account</h2>
                <p>Register a new staff member for QueueLess.</p>
            </div>
        </div>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    maxlength="100"
                    value="<?= e($name) ?>"
                    placeholder="Enter staff member's name"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    maxlength="191"
                    value="<?= e($email) ?>"
                    placeholder="staff@example.com"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="service_id">
                    Assign Campus Service
                </label>

                <select
                    id="service_id"
                    name="service_id"
                    class="form-control"
                >
                    <option value="">No service assignment yet</option>

                    <?php foreach ($services as $service): ?>
                        <?php if ($service['staff_id'] === null): ?>
                            <option
                                value="<?= e((string) $service['id']) ?>"
                                <?= $selectedService === (string) $service['id'] ? 'selected' : '' ?>
                            >
                                <?= e($service['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                Create Staff Account
            </button>
        </form>
    </section>

    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <h2>Registered Staff</h2>
                <p>Current staff accounts and service assignments.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Assigned Service</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$staffMembers): ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                No staff accounts registered yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $member): ?>
                            <tr>
                                <td><strong><?= e($member['name']) ?></strong></td>
                                <td><?= e($member['email']) ?></td>
                                <td><?= e($member['service_name'] ?? 'Not assigned') ?></td>
                                <td><?= e(date('d M Y', strtotime($member['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p>
        <a href="index.php" class="btn btn-secondary">
            Back to Admin Dashboard
        </a>
    </p>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>