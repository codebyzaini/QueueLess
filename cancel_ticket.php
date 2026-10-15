<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Student Queue Ticket Cancellation Controller
 *
 * Implements secure, student-owned ticket cancellation:
 * - Requires authenticated student session
 * - Verifies synchronizer CSRF token on POST requests
 * - Enforces strict ownership checks (student can only cancel their own ticket)
 * - Restricts cancellation strictly to 'waiting' tickets (cannot cancel serving or completed tickets)
 * - Preserves historical audit records by updating status to 'cancelled' rather than deleting rows
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Enforce authentication guard
require_login('login.php');
require_student();

// Reject non-POST requests immediately
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('home.php');
}

// 1. Verify CSRF Token
$csrfToken = $_POST['csrf_token'] ?? null;
if (!verify_csrf_token($csrfToken)) {
    set_flash('danger', 'Security token expired or invalid. Please try again.');
    redirect('home.php');
}

// 2. Validate Ticket Identifier
$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
if ($ticketId <= 0) {
    set_flash('danger', 'Invalid queue ticket specified.');
    redirect('home.php');
}

// 3. Obtain Student Identity from Session (never trust user input)
$user = current_user();
if (!$user || empty($user['id'])) {
    set_flash('danger', 'Unable to confirm student identity. Please sign in again.');
    redirect('login.php');
}
$studentId = (int)$user['id'];

// 4. Connect to Database
$pdo = get_db_connection();
if ($pdo === null) {
    set_flash('danger', 'Database service is currently unreachable. Please try again shortly.');
    redirect('home.php');
}

try {
    // Step A: Verify Ticket Ownership and Cancellation Eligibility
    $checkStmt = $pdo->prepare('
        SELECT qt.id, qt.token_number, qt.status, s.name AS service_name
        FROM queue_tickets qt
        JOIN services s ON qt.service_id = s.id
        WHERE qt.id = ? AND qt.student_id = ?
        LIMIT 1
    ');
    $checkStmt->execute([$ticketId, $studentId]);
    $ticket = $checkStmt->fetch();

    if (!$ticket) {
        set_flash('danger', 'Queue ticket not found or you are not authorized to cancel this ticket.');
        redirect('home.php');
    }

    if ($ticket['status'] !== 'waiting') {
        if ($ticket['status'] === 'serving') {
            set_flash('warning', 'Your token is currently being served at the counter and cannot be cancelled.');
        } else {
            set_flash('warning', 'This queue ticket is already ' . $ticket['status'] . ' and cannot be cancelled.');
        }
        redirect('home.php');
    }

    // Step B: Update Ticket Status to 'cancelled' with Finished Timestamp (Audit Preservation)
    $updateStmt = $pdo->prepare('
        UPDATE queue_tickets
        SET status = \'cancelled\', finished_at = NOW()
        WHERE id = ? AND student_id = ? AND status = \'waiting\'
    ');
    $updateStmt->execute([$ticketId, $studentId]);

    set_flash('info', 'Your queue ticket #' . $ticket['token_number'] . ' for ' . $ticket['service_name'] . ' has been cancelled.');
    redirect('home.php');

} catch (PDOException $e) {
    error_log('[QueueLess] Cancel Ticket Error: ' . $e->getMessage());
    set_flash('danger', 'An internal error occurred while cancelling your ticket. Please try again.');
    redirect('home.php');
}
