<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

require_staff();

function return_to_dashboard(string $type, string $message): never
{
    set_flash($type, $message);
    header('Location: /queueless/staff/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /queueless/staff/index.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    return_to_dashboard('error', 'Your session expired. Please try again.');
}

$action = $_POST['action'] ?? '';
$ticketId = filter_var(
    $_POST['ticket_id'] ?? null,
    FILTER_VALIDATE_INT
);

$pdo = get_db_connection();

if (!$pdo) {
    return_to_dashboard('error', 'Database connection failed.');
}

$staff = current_user();
$staffId = (int) $staff['id'];

try {
    $pdo->beginTransaction();

    // Lock the staff member's assigned service.
    $serviceQuery = $pdo->prepare(
        "SELECT id, name, is_open
         FROM services
         WHERE staff_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $serviceQuery->execute([$staffId]);
    $service = $serviceQuery->fetch();

    if (!$service) {
        throw new RuntimeException('No service is assigned to your account.');
    }

    $serviceId = (int) $service['id'];

    if ($action === 'call_next') {

        if ((int) $service['is_open'] !== 1) {
            throw new RuntimeException('Your service is currently closed.');
        }

        // Do not allow a second ticket to be served simultaneously.
        $currentQuery = $pdo->prepare(
            "SELECT id
             FROM queue_tickets
             WHERE service_id = ?
               AND status = 'serving'
             LIMIT 1
             FOR UPDATE"
        );
        $currentQuery->execute([$serviceId]);

        if ($currentQuery->fetch()) {
            throw new RuntimeException(
                'Complete or skip the current ticket before calling another student.'
            );
        }

        // Lock the oldest waiting ticket.
        $nextQuery = $pdo->prepare(
            "SELECT id, token_number
             FROM queue_tickets
             WHERE service_id = ?
               AND status = 'waiting'
             ORDER BY token_number ASC
             LIMIT 1
             FOR UPDATE"
        );
        $nextQuery->execute([$serviceId]);
        $nextTicket = $nextQuery->fetch();

        if (!$nextTicket) {
            throw new RuntimeException('There are no students waiting in your queue.');
        }

        $updateQuery = $pdo->prepare(
            "UPDATE queue_tickets
             SET status = 'serving',
                 called_at = NOW()
             WHERE id = ?
               AND service_id = ?
               AND status = 'waiting'"
        );
        $updateQuery->execute([
            (int) $nextTicket['id'],
            $serviceId
        ]);

        if ($updateQuery->rowCount() !== 1) {
            throw new RuntimeException('The ticket could not be called. Please try again.');
        }

        $pdo->commit();

        return_to_dashboard(
            'success',
            'Student with token #' . $nextTicket['token_number'] . ' has been called.'
        );

    } elseif ($action === 'complete' || $action === 'skip') {

        if (!$ticketId || $ticketId < 1) {
            throw new RuntimeException('Invalid ticket selected.');
        }

        // Only the currently serving ticket for this staff member's
        // assigned service can be completed or skipped.
        $ticketQuery = $pdo->prepare(
            "SELECT id, token_number
             FROM queue_tickets
             WHERE id = ?
               AND service_id = ?
               AND status = 'serving'
             LIMIT 1
             FOR UPDATE"
        );
        $ticketQuery->execute([$ticketId, $serviceId]);
        $ticket = $ticketQuery->fetch();

        if (!$ticket) {
            throw new RuntimeException(
                'This ticket is no longer being served or does not belong to your service.'
            );
        }

        $newStatus = ($action === 'complete') ? 'completed' : 'skipped';

        $updateQuery = $pdo->prepare(
            "UPDATE queue_tickets
             SET status = ?,
                 finished_at = NOW()
             WHERE id = ?
               AND service_id = ?
               AND status = 'serving'"
        );
        $updateQuery->execute([
            $newStatus,
            $ticketId,
            $serviceId
        ]);

        if ($updateQuery->rowCount() !== 1) {
            throw new RuntimeException('The ticket could not be updated. Please try again.');
        }

        $pdo->commit();

        if ($action === 'complete') {
            return_to_dashboard(
                'success',
                'Ticket #' . $ticket['token_number'] . ' has been completed.'
            );
        }

        return_to_dashboard(
            'success',
            'Ticket #' . $ticket['token_number'] . ' has been skipped.'
        );

    } else {
        throw new RuntimeException('Invalid queue action.');
    }

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('QueueLess staff action error: ' . $exception->getMessage());

    return_to_dashboard(
        'error',
        $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Something went wrong. Please try again.'
    );
}