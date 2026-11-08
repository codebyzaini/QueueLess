<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    exit('Database connection failed.');
}

$stats = [
    'students' => 0,
    'staff' => 0,
    'services' => 0,
    'waiting' => 0,
    'serving' => 0,
    'completed' => 0,
];

try {
    $stats['students'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'student'"
    )->fetchColumn();

    $stats['staff'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'staff'"
    )->fetchColumn();

    $stats['services'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM services"
    )->fetchColumn();

    $stats['waiting'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM queue_tickets WHERE status = 'waiting'"
    )->fetchColumn();

    $stats['serving'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM queue_tickets WHERE status = 'serving'"
    )->fetchColumn();

    $stats['completed'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM queue_tickets WHERE status IN ('completed', 'served')"
    )->fetchColumn();

    $services = $pdo->query("
        SELECT
            s.id,
            s.name,
            s.is_open,
            u.name AS staff_name,
            (
                SELECT COUNT(*)
                FROM queue_tickets q
                WHERE q.service_id = s.id
                  AND q.status = 'waiting'
            ) AS waiting_count,
            (
                SELECT q.token_number
                FROM queue_tickets q
                WHERE q.service_id = s.id
                  AND q.status = 'serving'
                ORDER BY q.called_at DESC, q.id DESC
                LIMIT 1
            ) AS serving_token
        FROM services s
        LEFT JOIN users u ON u.id = s.staff_id
        ORDER BY s.name ASC
    ")->fetchAll();

    $recentTickets = $pdo->query("
        SELECT
            q.token_number,
            q.status,
            q.joined_at,
            u.name AS student_name,
            s.name AS service_name
        FROM queue_tickets q
        INNER JOIN users u ON u.id = q.student_id
        INNER JOIN services s ON s.id = q.service_id
        ORDER BY q.joined_at DESC
        LIMIT 8
    ")->fetchAll();

} catch (PDOException $exception) {
    error_log('Admin dashboard error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to load dashboard information. Please check the application error log.');
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-wrapper">
    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
    <a href="staff.php" class="btn btn-primary">
        + Manage Staff
    </a>
</div>
    <section class="dashboard-header">
        <div>
            <span class="hero-badge-pill">ADMINISTRATION</span>
            <h1 class="dashboard-title">
                Admin Dashboard
            </h1>
            <p class="dashboard-subtitle">
                Monitor campus queues, services and system activity.
            </p>
        </div>
    </section>

    <section class="stats-grid" aria-label="System overview">

        <article class="stat-card">
            <span class="stat-label">Total Students</span>
            <strong class="stat-value"><?= e((string) $stats['students']) ?></strong>
            <span class="stat-caption">Registered student accounts</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Staff Members</span>
            <strong class="stat-value"><?= e((string) $stats['staff']) ?></strong>
            <span class="stat-caption">Registered staff accounts</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Campus Services</span>
            <strong class="stat-value"><?= e((string) $stats['services']) ?></strong>
            <span class="stat-caption">Available service counters</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Waiting Tickets</span>
            <strong class="stat-value"><?= e((string) $stats['waiting']) ?></strong>
            <span class="stat-caption">Students waiting in queues</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Currently Serving</span>
            <strong class="stat-value"><?= e((string) $stats['serving']) ?></strong>
            <span class="stat-caption">Active service tickets</span>
        </article>

        <article class="stat-card">
            <span class="stat-label">Completed Tickets</span>
            <strong class="stat-value"><?= e((string) $stats['completed']) ?></strong>
            <span class="stat-caption">Completed or served tickets</span>
        </article>

    </section>

    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <h2>Service Overview</h2>
                <p>Live overview of every campus service.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Assigned Staff</th>
                        <th>Current Token</th>
                        <th>Waiting</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                No services have been created yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td>
                                    <strong><?= e($service['name']) ?></strong>
                                </td>

                                <td>
                                    <?= e($service['staff_name'] ?? 'Not assigned') ?>
                                </td>

                                <td>
                                    <?php if ($service['serving_token'] !== null): ?>
                                        <span class="token-badge">
                                            #<?= e((string) $service['serving_token']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="muted-text">—</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= e((string) $service['waiting_count']) ?></td>

                                <td>
                                    <?php if ((int) $service['is_open'] === 1): ?>
                                        <span class="status-badge status-open">Open</span>
                                    <?php else: ?>
                                        <span class="status-badge status-closed">Closed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="dashboard-section">
        <div class="section-heading">
            <div>
                <h2>Recent Queue Activity</h2>
                <p>The eight most recently joined tickets.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Student</th>
                        <th>Service</th>
                        <th>Joined</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($recentTickets)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                No queue activity yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTickets as $ticket): ?>
                            <tr>
                                <td>
                                    <strong>#<?= e((string) $ticket['token_number']) ?></strong>
                                </td>

                                <td><?= e($ticket['student_name']) ?></td>

                                <td><?= e($ticket['service_name']) ?></td>

                                <td>
                                    <?= e(date('d M Y, h:i A', strtotime($ticket['joined_at']))) ?>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= e($ticket['status']) ?>">
                                        <?= e(ucfirst($ticket['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>