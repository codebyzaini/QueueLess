<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Student Homepage & Dashboard (Milestone 2)
 *
 * Protected route for authenticated students.
 * - Displays personalized greetings
 * - Retrieves student's active queue ticket (if one exists)
 * - Computes real-time queue position and dynamic wait-time estimates from MySQL
 * - Provides immediate ticket cancellation for eligible waiting tickets
 * - Displays real-time operational status for all campus department counters
 * - Enables "Join Queue" action for open counters under Single Active Ticket Policy
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Enforce authentication guard
require_login('login.php');
require_student();

$user = current_user();
$studentId = (int)$user['id'];
$services = [];
$activeTicket = null;
$peopleAhead = 0;
$dbError = null;

// Query live active ticket and campus services from MySQL
$pdo = get_db_connection();
if ($pdo === null) {
    $dbError = get_db_connection_error() ?? 'Database service is currently unreachable.';
} else {
    try {
        // 1. Fetch current active ticket for the authenticated student (if any)
        $ticketStmt = $pdo->prepare('
            SELECT qt.id, qt.service_id, qt.token_number, qt.status, qt.joined_at, qt.called_at,
                   s.name AS service_name, s.description AS service_description, s.is_open AS service_is_open
            FROM queue_tickets qt
            JOIN services s ON qt.service_id = s.id
            WHERE qt.student_id = ? AND qt.status IN (\'waiting\', \'serving\')
            ORDER BY qt.joined_at DESC
            LIMIT 1
        ');
        $ticketStmt->execute([$studentId]);
        $activeTicket = $ticketStmt->fetch();

        // 2. If student is waiting in line, calculate exact queue position ahead
        if ($activeTicket && $activeTicket['status'] === 'waiting') {
            $posStmt = $pdo->prepare('
                SELECT COUNT(*) AS people_ahead
                FROM queue_tickets
                WHERE service_id = ? AND status = \'waiting\' AND token_number < ?
            ');
            $posStmt->execute([(int)$activeTicket['service_id'], (int)$activeTicket['token_number']]);
            $posRow = $posStmt->fetch();
            $peopleAhead = (int)($posRow['people_ahead'] ?? 0);
        }

        // 3. Fetch all campus department counters ordered by availability
        $servicesStmt = $pdo->query('
            SELECT id, name, description, is_open, staff_id, created_at 
            FROM services 
            ORDER BY is_open DESC, name ASC
        ');
        $services = $servicesStmt->fetchAll();

    } catch (PDOException $e) {
        error_log('[QueueLess] Failed to fetch student dashboard data: ' . $e->getMessage());
        $dbError = 'Unable to query campus queue data from the database.';
    }
}

$pageTitle = 'Student Portal';
$activeNav = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <!-- Student Header & Summary Banner -->
    <div class="dashboard-header">
        <div class="dashboard-welcome">
            <h1>Welcome, <?= e($user['name']) ?>!</h1>
            <p>Campus Virtual Queue Portal & Department Status Overview</p>
        </div>
        <div class="dashboard-badges">
            <span class="badge badge-student">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
                Role: <?= e(ucfirst($user['role'])) ?>
            </span>
            <form action="logout.php" method="POST" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger" id="dashboard-logout-btn">
                    Sign Out
                </button>
            </form>
        </div>
    </div>

    <!-- Database Error Alert (Zero Fabricated Data) -->
    <?php if ($dbError !== null): ?>
        <div class="alert alert-danger" role="alert">
            <div class="alert-content">
                <strong>Database Notice:</strong> <?= e($dbError) ?>
                <div style="font-size: 0.8125rem; margin-top: 0.25rem;">
                    If you are configuring the application on Ubuntu, verify your database configuration in <code>/etc/queueless/database.php</code> and ensure the MySQL service is active.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Active Virtual Queue Ticket Section -->
    <div class="active-ticket-section">
        <div style="margin-bottom: var(--space-4);">
            <h2 style="font-size: 1.375rem; color: var(--color-navy); font-weight: 700;">
                Your Virtual Queue Status
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                Real-time queue tracking and token position. Arrive at the campus office when called.
            </p>
        </div>

        <?php if ($activeTicket !== null): ?>
            <!-- Live Active Ticket Card -->
            <div class="active-ticket-card" id="student-active-ticket">
                <div class="active-ticket-header">
                    <span class="active-ticket-header-title">
                        <?= e($activeTicket['service_name']) ?>
                    </span>
                    <div>
                        <?php if ($activeTicket['status'] === 'serving'): ?>
                            <span class="badge badge-serving">
                                <span class="status-dot status-dot-serving"></span> Called to Counter
                            </span>
                        <?php else: ?>
                            <span class="badge badge-waiting">
                                <span class="status-dot status-dot-waiting"></span> Waiting in Line
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($activeTicket['status'] === 'serving'): ?>
                    <div class="active-ticket-notice">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span><strong>Your token has been called!</strong> Please proceed to the <?= e($activeTicket['service_name']) ?> desk immediately.</span>
                    </div>
                <?php endif; ?>

                <div class="active-ticket-body">
                    <!-- Large Token Display -->
                    <div class="token-box">
                        <div class="token-box-number">#<?= (int)$activeTicket['token_number'] ?></div>
                        <div class="token-box-label">Virtual Token</div>
                        <div class="token-box-time">
                            Reserved: <?= date('g:i A', strtotime((string)$activeTicket['joined_at'])) ?>
                        </div>
                    </div>

                    <!-- Metrics and Actions -->
                    <div class="ticket-details">
                        <div class="ticket-metrics-grid">
                            <!-- Queue Position Metric -->
                            <div class="ticket-metric-card">
                                <div class="ticket-metric-value">
                                    <?php if ($activeTicket['status'] === 'serving'): ?>
                                        Counter
                                    <?php else: ?>
                                        #<?= $peopleAhead + 1 ?>
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-metric-label">Queue Position</div>
                                <div class="ticket-metric-note">
                                    <?php if ($activeTicket['status'] === 'serving'): ?>
                                        Being served now
                                    <?php elseif ($peopleAhead === 0): ?>
                                        You're next in line!
                                    <?php else: ?>
                                        <?= $peopleAhead ?> <?= $peopleAhead === 1 ? 'student' : 'students' ?> ahead
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- People Ahead Metric -->
                            <div class="ticket-metric-card">
                                <div class="ticket-metric-value">
                                    <?= $activeTicket['status'] === 'serving' ? '0' : $peopleAhead ?>
                                </div>
                                <div class="ticket-metric-label">Ahead of You</div>
                                <div class="ticket-metric-note">
                                    <?= $activeTicket['status'] === 'serving' ? 'Currently called' : 'In waiting line' ?>
                                </div>
                            </div>

                            <!-- Estimated Wait Time Metric -->
                            <div class="ticket-metric-card">
                                <div class="ticket-metric-value">
                                    <?php if ($activeTicket['status'] === 'serving'): ?>
                                        Now
                                    <?php elseif ($peopleAhead === 0): ?>
                                        ~2–5 min
                                    <?php else: ?>
                                        ~<?= ($peopleAhead * 5) ?>–<?= (($peopleAhead + 1) * 5) ?> min
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-metric-label">Est. Wait Time</div>
                                <div class="ticket-metric-note">
                                    <?= $activeTicket['status'] === 'serving' ? 'In consultation' : '~5 min / student' ?>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Footer Bar & Cancellation Action -->
                        <div class="ticket-footer-bar">
                            <div class="ticket-status-desc">
                                <?php if ($activeTicket['status'] === 'serving'): ?>
                                    Status: <strong>Active at Counter</strong> — Have your student ID card ready.
                                <?php else: ?>
                                    Status: <strong>Waiting</strong> — Keep this page open or arrive before your turn.
                                <?php endif; ?>
                            </div>

                            <?php if ($activeTicket['status'] === 'waiting'): ?>
                                <form action="cancel_ticket.php" method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to cancel your queue ticket? This action cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" id="cancel-ticket-btn">
                                        Cancel Ticket
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-serving">Cannot cancel while serving</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Informative Empty State When No Ticket Held -->
            <div class="no-ticket-card" id="no-active-ticket-banner">
                <div class="no-ticket-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="no-ticket-title">No Active Queue Ticket</h3>
                    <p class="no-ticket-desc">
                        You do not currently hold an active ticket. Review the campus department counters below and click <strong>Join Queue</strong> to reserve your virtual spot in line.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Campus Services Section -->
    <div class="services-section-header" style="margin-bottom: var(--space-6);">
        <h2 style="font-size: 1.375rem; color: var(--color-navy); font-weight: 700;">
            Available Campus Department Counters
        </h2>
        <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
            Real-time availability of administrative offices. Campus policy permits one active ticket per student across all offices.
        </p>
    </div>

    <?php if ($dbError === null && empty($services)): ?>
        <!-- Meaningful Empty State for Services -->
        <div class="empty-state-card">
            <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <h3 class="empty-state-title">No Campus Services Configured</h3>
            <p class="empty-state-desc">
                There are currently no active campus department counters registered in the database. Please run the sample seed script (<code>database/seed.sql</code>) or consult your system administrator.
            </p>
        </div>
    <?php elseif (!empty($services)): ?>
        <!-- Service Grid -->
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <?php 
                $isServiceOpen = ((int)$service['is_open'] === 1);
                $isCurrentServiceTicket = ($activeTicket !== null && (int)$service['id'] === (int)$activeTicket['service_id']);
                ?>
                <div class="service-card" id="service-card-<?= (int)$service['id'] ?>">
                    <div class="service-card-header">
                        <h3 class="service-name"><?= e($service['name']) ?></h3>
                        <?php if ($isServiceOpen): ?>
                            <span class="badge badge-open">
                                <span class="status-dot"></span> Open
                            </span>
                        <?php else: ?>
                            <span class="badge badge-closed">
                                Closed
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="service-description">
                        <?= e($service['description'] ?? 'No department description provided.') ?>
                    </p>

                    <div class="service-footer">
                        <span class="service-note">
                            <?= $isServiceOpen ? 'Accepting counter inquiries' : 'Counter currently unavailable' ?>
                        </span>

                        <?php if ($activeTicket !== null): ?>
                            <?php if ($isCurrentServiceTicket): ?>
                                <span class="badge badge-info">Current Queue</span>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline" disabled title="You already hold an active queue ticket. Cancel or complete your current ticket before joining another line.">
                                    Active Ticket Held
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($isServiceOpen): ?>
                                <form action="join_queue.php" method="POST" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-accent" id="join-queue-btn-<?= (int)$service['id'] ?>">
                                        Join Queue
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline" disabled title="This counter is temporarily closed">
                                    Closed
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Academic Scope & Milestone Notice -->
    <div style="background-color: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); margin-top: var(--space-8); font-size: 0.8125rem; color: var(--color-text-muted);">
        <strong style="color: var(--color-navy);">Milestone 2 Status:</strong> 
        Virtual queue reservations and student ticket cancellations are active. Dynamic positions and waiting estimates are calculated directly from MySQL records under the System-Wide Single Active Ticket Policy. Staff operator consoles and queue advancement tools are part of the next step.
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
