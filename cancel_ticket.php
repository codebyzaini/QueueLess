<?php
declare(strict_types=1);

/**
 * QueueLess — Smart Campus Queue Management System
 * Student Queue Ticket Cancellation Controller
 *
 * Security:
 * - Authenticated student access only
 * - CSRF validation
 * - Strict ticket ownership verification
 * - Transactional cancellation with row-level locking
 * - Only waiting tickets may be cancelled
 * - Historical records are preserved
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

require_login('/queueless/login.php');
require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/queueless/home.php');
}

// Validate CSRF token.
$csrfToken = $_POST['csrf_token'] ?? null;

if (!is_string($csrfToken) || !verify_csrf_token($csrfToken)) {
    set_flash('danger', 'Your security token has expired. Please refresh the page and try again.');
    redirect('/queueless/home.php');
}

// Validate ticket ID.
$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);

if ($ticketId === false || $ticketId === null || $ticketId <= 0) {
    set_flash('danger', 'Invalid queue ticket specified.');
    redirect('/queueless/home.php');
}

// Obtain student identity from the authenticated session.
$user = current_user();

if ($user === null || $user['id'] <= 0) {
    set_flash('danger', 'Unable to verify your account. Please sign in again.');
    redirect('/queueless/login.php');
}

$studentId = (int) $user['id'];

$pdo = get_db_connection();

if ($pdo === null) {
    set_flash('danger', 'The database is temporarily unavailable. Please try again shortly.');
    redirect('/queueless/home.php');
}

try {
    $pdo->beginTransaction();

    // Lock the student's ticket before checking its status.
    $checkStmt = $pdo->prepare("
        SELECT
            qt.id,
            qt.token_number,
            qt.status,
            s.name AS service_name
        FROM queue_tickets qt
        INNER JOIN services s ON s.id = qt.service_id
        WHERE qt.id = ?
          AND qt.student_id = ?
        FOR UPDATE
    ");

    $checkStmt->execute([$ticketId, $studentId]);

    $ticket = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $pdo->rollBack();

        set_flash(
            'danger',
            'Queue ticket not found or you are not authorized to cancel it.'
        );

        redirect('/queueless/home.php');
    }

    if ($ticket['status'] !== 'waiting') {
        $pdo->rollBack();

        if ($ticket['status'] === 'serving') {
            set_flash(
                'warning',
                'Your ticket is currently being served and cannot be cancelled.'
            );
        } else {
            set_flash(
                'warning',
                'This ticket is already ' . $ticket['status'] . ' and cannot be cancelled.'
            );
        }

        redirect('/queueless/home.php');
    }

    // Conditional update provides an additional safeguard.
    $updateStmt = $pdo->prepare("
        UPDATE queue_tickets
        SET
            status = 'cancelled',
            finished_at = NOW()
        WHERE id = ?
          AND student_id = ?
          AND status = 'waiting'
    ");

    $updateStmt->execute([$ticketId, $studentId]);

    if ($updateStmt->rowCount() !== 1) {
        $pdo->rollBack();

        set_flash(
            'warning',
            'Your ticket could not be cancelled because its status has changed. Please refresh the dashboard.'
        );

        redirect('/queueless/home.php');
    }

    $pdo->commit();

    set_flash(
        'success',
        'Your ticket #' . (int) $ticket['token_number']
        . ' for ' . $ticket['service_name']
        . ' has been successfully cancelled.'
    );

    redirect('/queueless/home.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[QueueLess] Cancel Ticket Transaction Error: ' . $e->getMessage());

    set_flash(
        'danger',
        'An internal database error occurred while cancelling your ticket. Please try again.'
    );

    redirect('/queueless/home.php');
}