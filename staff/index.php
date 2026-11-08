<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

require_staff();

$pdo = get_db_connection();

if (!$pdo) {
    exit('Database connection failed.');
}

$staff = current_user();
$staffId = (int) $staff['id'];

$serviceQuery = $pdo->prepare(
    "SELECT id, name, description, is_open
     FROM services
     WHERE staff_id = ?
     LIMIT 1"
);
$serviceQuery->execute([$staffId]);
$service = $serviceQuery->fetch();

$currentTicket = null;
$waitingTickets = [];
$recentTickets = [];
$waitingCount = 0;

if ($service) {
    $serviceId = (int) $service['id'];

    $currentQuery = $pdo->prepare(
        "SELECT q.id, q.token_number, q.joined_at, q.called_at,
                u.name AS student_name, u.email AS student_email
         FROM queue_tickets q
         JOIN users u ON u.id = q.student_id
         WHERE q.service_id = ? AND q.status = 'serving'
         ORDER BY q.called_at DESC, q.id DESC
         LIMIT 1"
    );
    $currentQuery->execute([$serviceId]);
    $currentTicket = $currentQuery->fetch();

    $waitingQuery = $pdo->prepare(
        "SELECT q.id, q.token_number, q.joined_at,
                u.name AS student_name
         FROM queue_tickets q
         JOIN users u ON u.id = q.student_id
         WHERE q.service_id = ? AND q.status = 'waiting'
         ORDER BY q.token_number ASC
         LIMIT 20"
    );
    $waitingQuery->execute([$serviceId]);
    $waitingTickets = $waitingQuery->fetchAll();

    $countQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM queue_tickets
         WHERE service_id = ? AND status = 'waiting'"
    );
    $countQuery->execute([$serviceId]);
    $waitingCount = (int) $countQuery->fetchColumn();

    $historyQuery = $pdo->prepare(
        "SELECT q.token_number, q.status, q.joined_at,
                u.name AS student_name
         FROM queue_tickets q
         JOIN users u ON u.id = q.student_id
         WHERE q.service_id = ?
         ORDER BY q.joined_at DESC
         LIMIT 10"
    );
    $historyQuery->execute([$serviceId]);
    $recentTickets = $historyQuery->fetchAll();
}

$pageTitle = 'Staff Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.staff-page {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 24px 50px;
    color: #172b4d;
}
.staff-page * { box-sizing: border-box; }
.staff-heading { margin-bottom: 28px; }
.staff-heading h1 { font-size: 32px; margin: 12px 0; color: #10213a; }
.staff-heading p { color: #667085; }
.staff-tag {
    display: inline-block;
    background: #eaf2ff;
    color: #175cd3;
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.staff-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin: 25px 0;
}
.staff-card, .staff-panel {
    background: white;
    border: 1px solid #e4eaf2;
    border-radius: 14px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(16, 33, 58, .04);
}
.staff-card span { display: block; color: #667085; font-size: 14px; }
.staff-card strong { display: block; font-size: 30px; margin: 12px 0; color: #175cd3; }
.staff-panel { margin-top: 22px; }
.staff-panel h2 { font-size: 21px; margin: 0 0 8px; color: #10213a; }
.staff-muted { color: #667085; font-size: 14px; }
.staff-current {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 22px;
    border-radius: 12px;
    margin-top: 18px;
}
.staff-token { font-size: 42px; font-weight: 800; color: #175cd3; margin: 12px 0; }
.staff-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
.staff-btn {
    display: inline-block;
    border: 0;
    border-radius: 8px;
    padding: 12px 19px;
    background: #175cd3;
    color: white !important;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}
.staff-btn-danger { background: #c43232; }
.staff-btn:disabled { opacity: .45; cursor: not-allowed; }
.staff-table-wrap { width: 100%; overflow-x: auto; margin-top: 18px; }
.staff-table { width: 100%; border-collapse: collapse; min-width: 520px; }
.staff-table th, .staff-table td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e8edf3;
    font-size: 14px;
}
.staff-table th { color: #667085; background: #f8fafc; }
.staff-status {
    display: inline-block;
    border-radius: 20px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
    background: #eaf2ff;
    color: #175cd3;
}
.staff-status.open { background: #dcfce7; color: #15803d; }
.staff-status.closed { background: #fee2e2; color: #b42318; }
.staff-alert { padding: 13px 16px; border-radius: 9px; margin: 15px 0; }
.staff-success { background: #dcfce7; color: #166534; }
.staff-error { background: #fee2e2; color: #991b1b; }
.staff-empty { padding: 25px 10px; text-align: center; color: #667085; }
@media(max-width:700px) {
    .staff-stats { grid-template-columns: 1fr; }
    .staff-heading h1 { font-size: 25px; }
    .staff-page { padding: 0 15px 35px; }
}
</style>

<main class="staff-page">

    <section class="staff-heading">
        <span class="staff-tag">STAFF PORTAL</span>
        <h1>Welcome, <?= e($staff['name']) ?></h1>
        <p>Manage your assigned campus service and process student tickets.</p>
    </section>

   
   

    <?php if (!$service): ?>

        <section class="staff-panel">
            <h2>No Service Assigned</h2>
            <p>Please contact your administrator to assign a service.</p>
        </section>

    <?php else: ?>

        <section class="staff-stats">

            <article class="staff-card">
                <span>Assigned Service</span>
                <strong style="font-size:22px;">
                    <?= e($service['name']) ?>
                </strong>
                <span>Your campus counter</span>
            </article>

            <article class="staff-card">
                <span>Waiting Students</span>
                <strong><?= e((string) $waitingCount) ?></strong>
                <span>Students in your queue</span>
            </article>

            <article class="staff-card">
                <span>Currently Serving</span>
                <strong>
                    <?= $currentTicket
                        ? '#' . e((string) $currentTicket['token_number'])
                        : '—' ?>
                </strong>
                <span>Current ticket</span>
            </article>

        </section>

        <section class="staff-panel">

            <h2>Service Control</h2>
            <p class="staff-muted"><?= e($service['name']) ?></p>

            <p>
                Service status:
                <?php if ((int) $service['is_open'] === 1): ?>
                    <span class="staff-status open">OPEN</span>
                <?php else: ?>
                    <span class="staff-status closed">CLOSED</span>
                <?php endif; ?>
            </p>

            <?php if ($currentTicket): ?>

                <div class="staff-current">
                    <span class="staff-muted">NOW SERVING</span>

                    <div class="staff-token">
                        #<?= e((string) $currentTicket['token_number']) ?>
                    </div>

                    <p>
                        <strong>Student:</strong>
                        <?= e($currentTicket['student_name']) ?>
                    </p>

                    <p>
                        <strong>Email:</strong>
                        <?= e($currentTicket['student_email']) ?>
                    </p>

                    <div class="staff-actions">

                        <form method="POST" action="queue_action.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="complete">
                            <input type="hidden" name="ticket_id"
                                   value="<?= e((string) $currentTicket['id']) ?>">
                            <button class="staff-btn" type="submit">
                                Complete Ticket
                            </button>
                        </form>

                        <form method="POST" action="queue_action.php"
                              onsubmit="return confirm('Skip this ticket?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="skip">
                            <input type="hidden" name="ticket_id"
                                   value="<?= e((string) $currentTicket['id']) ?>">
                            <button class="staff-btn staff-btn-danger" type="submit">
                                Skip Ticket
                            </button>
                        </form>

                    </div>
                </div>

            <?php else: ?>

                <div class="staff-empty">
                    <h3>No student currently being served</h3>
                    <p>Call the next waiting student to begin service.</p>
                </div>

            <?php endif; ?>

            <div class="staff-actions">
                <form method="POST" action="queue_action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="call_next">
                    <button
                        type="submit"
                        class="staff-btn"
                        <?= ((int) $service['is_open'] !== 1 || $currentTicket || $waitingCount === 0) ? 'disabled' : '' ?>>
                        Call Next Student
                    </button>
                </form>
            </div>

        </section>

        <section class="staff-panel">
            <h2>Waiting Queue</h2>
            <p class="staff-muted">Students waiting for your service.</p>

            <div class="staff-table-wrap">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Student</th>
                            <th>Joined At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if (!$waitingTickets): ?>
                        <tr>
                            <td colspan="4" class="staff-empty">
                                No students are waiting.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($waitingTickets as $ticket): ?>
                            <tr>
                                <td><strong>#<?= e((string) $ticket['token_number']) ?></strong></td>
                                <td><?= e($ticket['student_name']) ?></td>
                                <td><?= e(date('h:i A', strtotime($ticket['joined_at']))) ?></td>
                                <td><span class="staff-status">Waiting</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

        <section class="staff-panel">
            <h2>Recent Queue History</h2>
            <p class="staff-muted">Latest ten tickets for your service.</p>

            <div class="staff-table-wrap">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Student</th>
                            <th>Joined</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php if (!$recentTickets): ?>
                        <tr>
                            <td colspan="4" class="staff-empty">
                                No queue history available.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTickets as $ticket): ?>
                            <tr>
                                <td><strong>#<?= e((string) $ticket['token_number']) ?></strong></td>
                                <td><?= e($ticket['student_name']) ?></td>
                                <td><?= e(date('d M Y, h:i A', strtotime($ticket['joined_at']))) ?></td>
                                <td>
                                    <span class="staff-status">
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

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>