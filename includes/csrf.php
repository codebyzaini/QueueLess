<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Cross-Site Request Forgery (CSRF) Protection
 *
 * Implements synchronizer token pattern to validate that POST submissions
 * originate intentionally from the authenticated user's session.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Retrieve or generate a cryptographically secure CSRF token for the current session.
 *
 * @return string
 */
function csrf_token(): string {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Generate an HTML hidden input element containing the current CSRF token.
 *
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a submitted CSRF token against the session token.
 * Uses timing-attack resistant comparison (hash_equals).
 *
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    init_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rotate the CSRF token in the active session.
 * Regenerates the synchronizer token upon privilege or authentication boundaries
 * to prevent token fixation or cross-session token reuse.
 *
 * @return string The newly generated CSRF token
 */
function rotate_csrf_token(): string {
    init_session();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}
