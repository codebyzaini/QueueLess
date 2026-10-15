<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Session Termination (Logout)
 *
 * Implements secure logout lifecycle:
 * - Requires HTTP POST method to prevent CSRF logout via embedded img tags
 * - Validates synchronizer CSRF token
 * - Clears session superglobal, destroys session store, and invalidates session cookie
 * - Safely redirects user to login.php
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Only process state changes via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;

    if (verify_csrf_token($token)) {
        logout_user();

        // Queue exit notification in the clean anonymous session
        set_flash('info', 'You have been safely logged out of your session.');
        redirect('login.php');
    } else {
        // Invalid or expired CSRF token
        set_flash('warning', 'Session security token expired. Please try logging out again.');
        redirect('home.php');
    }
} else {
    // Direct GET access to logout endpoint is rejected to prevent login-state disruption
    redirect('home.php');
}
