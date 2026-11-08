<?php
declare(strict_types=1);

/**
 * QueueLess — Smart Campus Queue Management System
 * Student Dashboard
 *
 * Features:
 * - Protected student access
 * - Active queue ticket tracking
 * - Real queue position calculation
 * - Live service availability from MySQL
 * - Ticket cancellation for waiting tickets
 * - Recent ticket history
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Only authenticated students may access this page.
require_login();
require_student();

$user = current_user();
$studentId = (int) $user['id'];

$pdo = null;
$dbError = null;

$services = [];
$activeTicket = null;
$recentTickets = [];
$peopleAhead = 0;

try {
    $pdo = get_db_connection();

    if ($pdo === null) {
        throw new RuntimeException(
            get_db_connection_error() ?? 'Database connection is unavailable.'
        );
    }

    /*
     * 1. Retrieve the student's active ticket.
     */
    $ticketStmt = $pdo->prepare("
        SELECT
            qt.id,
            qt.service_id,
            qt.token_number,
            qt.status,
            qt.joined_at,
            qt.called_at,
            s.name AS service_name,
            s.description AS service_description,
            s.is_open AS service_is_open
        FROM queue_tickets qt
        INNER JOIN services s ON s.id = qt.service_id
        WHERE qt.student_id = :student_id
          AND qt.status IN ('waiting', 'serving')
        ORDER BY qt.joined_at DESC, qt.id DESC
        LIMIT 1
    ");

    $ticketStmt->execute([
        'student_id' => $studentId,
    ]);

    $activeTicket = $ticketStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    /*
     * 2. Calculate the number of people ahead.
     *
     * Both waiting and serving tickets are counted when their
     * token number is lower than the student's token number.
     */
    if (
        $activeTicket !== null
        && $activeTicket['status'] === 'waiting'
    ) {
        $positionStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM queue_tickets
            WHERE service_id = :service_id
              AND status IN ('waiting', 'serving')
              AND token_number < :token_number
        ");

        $positionStmt->execute([
            'service_id' => (int) $activeTicket['service_id'],
            'token_number' => (int) $activeTicket['token_number'],
        ]);

        $peopleAhead = (int) $positionStmt->fetchColumn();
    }

    /*
     * 3. Retrieve campus services.
     */
    $servicesStmt = $pdo->query("
        SELECT
            id,
            name,
            description,
            is_open,
            staff_id
        FROM services
        ORDER BY is_open DESC, name ASC
    ");

    $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * 4. Retrieve the student's five most recent inactive tickets.
     */
    $historyStmt = $pdo->prepare("
        SELECT
            qt.id,
            qt.token_number,
            qt.status,
            qt.joined_at,
            qt.finished_at,
            s.name AS service_name
        FROM queue_tickets qt
        INNER JOIN services s ON s.id = qt.service_id
        WHERE qt.student_id = :student_id
          AND qt.status IN (
              'completed',
              'served',
              'cancelled',
              'skipped'
          )
        ORDER BY
            COALESCE(qt.finished_at, qt.joined_at) DESC,
            qt.id DESC
        LIMIT 5
    ");

    $historyStmt->execute([
        'student_id' => $studentId,
    ]);

    $recentTickets = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        '[QueueLess] Student dashboard error: ' . $e->getMessage()
    );

    $dbError = 'We could not retrieve your queue information. Please try again shortly.';
}

$pageTitle = 'Student Dashboard';
$activeNav = 'home';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">

    <!-- Dashboard Welcome -->
    <section class="dashboard-header">

        <div class="dashboard-welcome">
            <h1>Welcome, <?= e($user['name']) ?>!</h1>

            <p>
                Manage your campus visits and monitor your queue status
                from one place.
            </p>
        </div>

        <div class="dashboard-badges">

            <span class="badge badge-student">
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    aria-hidden="true"
                >
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>

                Student Account
            </span>

        </div>

    </section>


    <!-- Database Error -->
    <?php if ($dbError !== null): ?>

        <div class="alert alert-danger" role="alert">

            <div class="alert-content">
                <strong>Dashboard unavailable.</strong>

                <p><?= e($dbError) ?></p>

                <button
                    type="button"
                    class="btn btn-sm btn-outline"
                    onclick="window.location.reload()"
                >
                    Try Again
                </button>
            </div>

        </div>

    <?php endif; ?>


    <?php if ($dbError === null): ?>

        <!-- Active Ticket Section -->
        <section class="active-ticket-section">

            <div style="margin-bottom: var(--space-4);">

                <h2 style="font-size: 1.375rem; color: var(--color-navy); font-weight: 700;">
                    Your Queue Status
                </h2>

                <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                    Track your current ticket and check your position
                    in the department queue.
                </p>

            </div>


            <?php if ($activeTicket !== null): ?>

                <!-- Active Ticket Card -->
                <div
                    class="active-ticket-card"
                    id="student-active-ticket"
                    data-ticket-id="<?= (int) $activeTicket['id'] ?>"
                    data-ticket-status="<?= e($activeTicket['status']) ?>"
                >

                    <div class="active-ticket-header">

                        <span class="active-ticket-header-title">
                            <?= e($activeTicket['service_name']) ?>
                        </span>

                        <?php if ($activeTicket['status'] === 'serving'): ?>

                            <span class="badge badge-serving">
                                <span class="status-dot status-dot-serving"></span>
                                Called to Counter
                            </span>

                        <?php else: ?>

                            <span class="badge badge-waiting">
                                <span class="status-dot status-dot-waiting"></span>
                                Waiting in Line
                            </span>

                        <?php endif; ?>

                    </div>


                    <?php if ($activeTicket['status'] === 'serving'): ?>

                        <div class="active-ticket-notice">

                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>

                            <span>
                                <strong>Your token has been called!</strong>
                                Please proceed to the
                                <?= e($activeTicket['service_name']) ?>
                                counter.
                            </span>

                        </div>

                    <?php endif; ?>


                    <div class="active-ticket-body">

                        <!-- Token -->
                        <div class="token-box">

                            <div class="token-box-number">
                                #<?= (int) $activeTicket['token_number'] ?>
                            </div>

                            <div class="token-box-label">
                                Your Virtual Token
                            </div>

                            <div class="token-box-time">

                                Joined:
                                <?= e(date(
                                    'g:i A',
                                    strtotime((string) $activeTicket['joined_at'])
                                )) ?>

                            </div>

                        </div>


                        <!-- Ticket Information -->
                        <div class="ticket-details">

                            <div class="ticket-metrics-grid">

                                <!-- Queue Position -->
                                <div class="ticket-metric-card">

                                    <div class="ticket-metric-value">

                                        <?php if ($activeTicket['status'] === 'serving'): ?>

                                            Now

                                        <?php else: ?>

                                            #<?= $peopleAhead + 1 ?>

                                        <?php endif; ?>

                                    </div>

                                    <div class="ticket-metric-label">
                                        Queue Position
                                    </div>

                                    <div class="ticket-metric-note">

                                        <?php if ($activeTicket['status'] === 'serving'): ?>

                                            Currently being served

                                        <?php elseif ($peopleAhead === 0): ?>

                                            You are next in line!

                                        <?php else: ?>

                                            <?= $peopleAhead ?>
                                            <?= $peopleAhead === 1 ? 'person' : 'people' ?>
                                            ahead of you

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- People Ahead -->
                                <div class="ticket-metric-card">

                                    <div class="ticket-metric-value">
                                        <?= $activeTicket['status'] === 'serving'
                                            ? '0'
                                            : $peopleAhead ?>
                                    </div>

                                    <div class="ticket-metric-label">
                                        People Ahead
                                    </div>

                                    <div class="ticket-metric-note">
                                        In your department queue
                                    </div>

                                </div>


                                <!-- Ticket Status -->
                                <div class="ticket-metric-card">

                                    <div class="ticket-metric-value">

                                        <?= $activeTicket['status'] === 'serving'
                                            ? 'Called'
                                            : 'Waiting' ?>

                                    </div>

                                    <div class="ticket-metric-label">
                                        Ticket Status
                                    </div>

                                    <div class="ticket-metric-note">
                                        Current ticket state
                                    </div>

                                </div>

                            </div>


                            <!-- Ticket Actions -->
                            <div class="ticket-footer-bar">

                                <div class="ticket-status-desc">

                                    <?php if ($activeTicket['status'] === 'serving'): ?>

                                        <strong>Now serving:</strong>
                                        Please visit the counter.

                                    <?php else: ?>

                                        <strong>Waiting:</strong>
                                        Please keep your ticket available.

                                    <?php endif; ?>

                                </div>


                                <?php if ($activeTicket['status'] === 'waiting'): ?>

                                    <form
                                        action="cancel_ticket.php"
                                        method="POST"
                                        class="inline-form"
                                        onsubmit="return confirm('Are you sure you want to cancel this ticket?');"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="ticket_id"
                                            value="<?= (int) $activeTicket['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            id="cancel-ticket-btn"
                                        >
                                            Cancel Ticket
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="badge badge-serving">
                                        Cancellation unavailable
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>


            <?php else: ?>

                <!-- No Active Ticket -->
                <div
                    class="no-ticket-card"
                    id="no-active-ticket-banner"
                >

                    <div class="no-ticket-icon">

                        <svg
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <rect x="2" y="7" width="20" height="14" rx="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>

                    </div>

                    <div>

                        <h3 class="no-ticket-title">
                            No Active Queue Ticket
                        </h3>

                        <p class="no-ticket-desc">
                            You do not currently have an active ticket.
                            Select an available campus department below
                            to join its queue.
                        </p>

                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- Campus Services -->
        <section class="services-section">

            <div
                class="services-section-header"
                style="margin-bottom: var(--space-6);"
            >

                <h2 style="font-size: 1.375rem; color: var(--color-navy); font-weight: 700;">
                    Campus Departments
                </h2>

                <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                    View department availability and join an open queue.
                    Only one active ticket is permitted per student.
                </p>

            </div>


            <?php if (empty($services)): ?>

                <div class="empty-state-card">

                    <svg
                        class="empty-state-icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>

                    <h3 class="empty-state-title">
                        No Departments Available
                    </h3>

                    <p class="empty-state-desc">
                        No campus departments have been configured.
                        Please contact your administrator.
                    </p>

                </div>

            <?php else: ?>

                <div class="services-grid">

                    <?php foreach ($services as $service): ?>

                        <?php
                        $isServiceOpen = (int) $service['is_open'] === 1;

                        $isCurrentService = (
                            $activeTicket !== null
                            && (int) $activeTicket['service_id']
                                === (int) $service['id']
                        );
                        ?>

                        <article
                            class="service-card"
                            id="service-card-<?= (int) $service['id'] ?>"
                        >

                            <div class="service-card-header">

                                <h3 class="service-name">
                                    <?= e($service['name']) ?>
                                </h3>

                                <?php if ($isServiceOpen): ?>

                                    <span class="badge badge-open">
                                        <span class="status-dot"></span>
                                        Open
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-closed">
                                        Closed
                                    </span>

                                <?php endif; ?>

                            </div>


                            <p class="service-description">
                                <?= e(
                                    $service['description']
                                    ?: 'No department description available.'
                                ) ?>
                            </p>


                            <div class="service-footer">

                                <span class="service-note">

                                    <?= $isServiceOpen
                                        ? 'Accepting queue requests'
                                        : 'Currently unavailable' ?>

                                </span>


                                <?php if ($activeTicket !== null): ?>

                                    <?php if ($isCurrentService): ?>

                                        <span class="badge badge-info">
                                            Your Current Queue
                                        </span>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline"
                                            disabled
                                            title="You already have an active ticket."
                                        >
                                            Active Ticket Held
                                        </button>

                                    <?php endif; ?>


                                <?php elseif ($isServiceOpen): ?>

                                    <form
                                        action="join_queue.php"
                                        method="POST"
                                        class="inline-form"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="service_id"
                                            value="<?= (int) $service['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-accent"
                                            id="join-queue-btn-<?= (int) $service['id'] ?>"
                                        >
                                            Join Queue
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline"
                                        disabled
                                    >
                                        Closed
                                    </button>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>


        <!-- Recent Ticket History -->
        <section class="ticket-history-section" style="margin-top: var(--space-8);">

            <div class="services-section-header" style="margin-bottom: var(--space-4);">

                <h2 style="font-size: 1.375rem; color: var(--color-navy); font-weight: 700;">
                    Recent Ticket History
                </h2>

                <p style="color: var(--color-text-muted); font-size: 0.9375rem;">
                    Your five most recent completed, cancelled, or skipped tickets.
                </p>

            </div>


            <?php if (empty($recentTickets)): ?>

                <div class="empty-state-card">

                    <h3 class="empty-state-title">
                        No Previous Tickets
                    </h3>

                    <p class="empty-state-desc">
                        Your ticket history will appear here after
                        you finish or cancel a queue ticket.
                    </p>

                </div>

            <?php else: ?>

                <div class="history-table-wrapper" style="overflow-x: auto;">

                    <table style="width: 100%; border-collapse: collapse; text-align: left;">

                        <thead>

                            <tr>
                                <th style="padding: 14px;">Token</th>
                                <th style="padding: 14px;">Department</th>
                                <th style="padding: 14px;">Joined</th>
                                <th style="padding: 14px;">Status</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($recentTickets as $ticket): ?>

                                <?php
                                $status = (string) $ticket['status'];

                                $statusLabel = match ($status) {
                                    'completed', 'served' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                    'skipped' => 'Skipped',
                                    default => ucfirst($status),
                                };
                                ?>

                                <tr>

                                    <td style="padding: 14px;">
                                        #<?= (int) $ticket['token_number'] ?>
                                    </td>

                                    <td style="padding: 14px;">
                                        <?= e($ticket['service_name']) ?>
                                    </td>

                                    <td style="padding: 14px;">
                                        <?= e(date(
                                            'd M Y, g:i A',
                                            strtotime((string) $ticket['joined_at'])
                                        )) ?>
                                    </td>

                                    <td style="padding: 14px;">
                                        <span class="badge badge-info">
                                            <?= e($statusLabel) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>