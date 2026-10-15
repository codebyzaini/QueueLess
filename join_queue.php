<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Student Queue Join Controller
 *
 * Implements concurrency-safe queue ticket reservations:
 * - Requires authenticated student session (role elevation immune)
 * - Verifies synchronizer CSRF tokens on state-altering POST requests
 * - Enforces the System-Wide Single Active Ticket Policy
 * - Uses InnoDB row-level locking (SELECT ... FOR UPDATE) to prevent
 *   duplicate token collisions under concurrent student joins
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
    set_flash('danger', 'Security token expired or invalid. Please reload the page and try again.');
    redirect('home.php');
}

// 2. Validate Service Identifier
$serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
if ($serviceId <= 0) {
    set_flash('danger', 'Please select a valid campus department counter.');
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
    // Begin strict database transaction
    $pdo->beginTransaction();

    // Step A: Lock the authenticated student row first. This serializes concurrent
    // first-ticket attempts even when the student currently has no active ticket row.
    $studentLockStmt = $pdo->prepare('
        SELECT id
        FROM users
        WHERE id = ? AND role = \'student\'
        FOR UPDATE
    ');
    $studentLockStmt->execute([$studentId]);

    if (!$studentLockStmt->fetch()) {
        $pdo->rollBack();
        set_flash('danger', 'Your account is not authorized to join student queues.');
        redirect('home.php');
    }

    // Step B: Lock and verify the target campus service
    $serviceStmt = $pdo->prepare('
        SELECT id, name, is_open 
        FROM services 
        WHERE id = ? 
        FOR UPDATE
    ');
    $serviceStmt->execute([$serviceId]);
    $service = $serviceStmt->fetch();

    if (!$service) {
        $pdo->rollBack();
        set_flash('danger', 'The selected campus department could not be found.');
        redirect('home.php');
    }

    if ((int)$service['is_open'] !== 1) {
        $pdo->rollBack();
        set_flash('warning', 'The ' . $service['name'] . ' counter is currently closed and not accepting new queue tickets.');
        redirect('home.php');
    }

    // Step C: Enforce System-Wide Single Active Ticket Policy
    // Prevent students from holding multiple active tokens across the campus
    $activeStmt = $pdo->prepare('
        SELECT qt.id, s.name AS service_name
        FROM queue_tickets qt
        JOIN services s ON qt.service_id = s.id
        WHERE qt.student_id = ? AND qt.status IN (\'waiting\', \'serving\')
        FOR UPDATE
    ');
    $activeStmt->execute([$studentId]);
    $existingTicket = $activeStmt->fetch();

    if ($existingTicket) {
        $pdo->rollBack();
        set_flash('warning', 'You already hold an active queue ticket for ' . $existingTicket['service_name'] . '. Please cancel or complete your current ticket before joining another line.');
        redirect('home.php');
    }

    // Step D: Concurrency-Safe Sequential Token Allocation
    // Because the service row was locked via SELECT ... FOR UPDATE above,
    // concurrent joins for this specific service are serialized cleanly.
    $tokenStmt = $pdo->prepare('
        SELECT COALESCE(MAX(token_number), 0) + 1 AS next_token
        FROM queue_tickets
        WHERE service_id = ?
    ');
    $tokenStmt->execute([$serviceId]);
    $tokenRow = $tokenStmt->fetch();
    $nextToken = (int)($tokenRow['next_token'] ?? 1);

    // Step E: Insert the new virtual queue ticket
    $insertStmt = $pdo->prepare('
        INSERT INTO queue_tickets (student_id, service_id, token_number, status, joined_at)
        VALUES (?, ?, ?, \'waiting\', NOW())
    ');
    $insertStmt->execute([$studentId, $serviceId, $nextToken]);

    // Commit transaction to release row lock and finalize ticket creation
    $pdo->commit();

    set_flash('success', 'You have successfully joined the queue for ' . $service['name'] . '! Your token number is #' . $nextToken . '.');
    redirect('home.php');

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[QueueLess] Join Queue Transaction Error: ' . $e->getMessage());
    set_flash('danger', 'An internal database error occurred while reserving your ticket. Please try again.');
    redirect('home.php');
}
