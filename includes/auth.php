<?php
declare(strict_types=1);

/**
 * QueueLess — Smart Campus Queue Management System
 * Authentication & Session Management
 *
 * Supports:
 * - Student, Staff, and Admin authentication
 * - Role-based dashboard routing
 * - Protected portal access
 * - Session fixation protection
 * - CSRF token rotation
 * - Secure logout
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

/**
 * Return the dashboard URL for a user's role.
 */
function dashboard_url(string $role): string
{
    return match ($role) {
        'admin'   => '/queueless/admin/index.php',
        'staff'   => '/queueless/staff/index.php',
        'student' => '/queueless/home.php',
        default   => '/queueless/login.php',
    };
}

/**
 * Check whether the current session is authenticated.
 */
function is_logged_in(): bool
{
    init_session();

    return isset($_SESSION['user_id'])
        && (int) $_SESSION['user_id'] > 0;
}

/**
 * Retrieve the currently authenticated user's information.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $role = (string) ($_SESSION['user_role'] ?? '');

    if (!in_array($role, ['student', 'staff', 'admin'], true)) {
        return null;
    }

    return [
        'id'    => (int) $_SESSION['user_id'],
        'name'  => (string) ($_SESSION['user_name'] ?? 'User'),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'role'  => $role,
    ];
}

/**
 * Require an authenticated user.
 */
function require_login(string $redirectTo = '/queueless/login.php'): void
{
    if (!is_logged_in() || current_user() === null) {
        set_flash('warning', 'Please sign in to continue.');
        redirect($redirectTo);
    }
}

/**
 * Require a particular user role.
 */
function require_role(string $role): void
{
    require_login();

    $user = current_user();

    if ($user === null || $user['role'] !== $role) {
        http_response_code(403);
        exit('Access denied. You do not have permission to access this page.');
    }
}

/**
 * Require student access.
 */
function require_student(): void
{
    require_role('student');
}

/**
 * Require staff access.
 */
function require_staff(): void
{
    require_role('staff');
}

/**
 * Require administrator access.
 */
function require_admin(): void
{
    require_role('admin');
}

/**
 * Prevent authenticated users from accessing guest-only pages.
 */
function require_guest(string $redirectTo = ''): void
{
    $user = current_user();

    if ($user !== null) {
        redirect(
            $redirectTo !== ''
                ? $redirectTo
                : dashboard_url($user['role'])
        );
    }
}

/**
 * Create an authenticated session.
 */
function login_user(array $user): void
{
    init_session();

    $role = (string) ($user['role'] ?? '');

    if (!in_array($role, ['student', 'staff', 'admin'], true)) {
        throw new InvalidArgumentException('Invalid user role.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('Unable to establish user session.');
    }

    // Prevent session fixation.
    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate session ID.');
    }

    // Rotate CSRF token after authentication.
    rotate_csrf_token();

    // Store authenticated user information.
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = (string) $user['name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role']  = $role;
}

/**
 * Safely terminate the authenticated session.
 */
function logout_user(): void
{
    init_session();

    // Clear all session data.
    $_SESSION = [];

    // Expire the existing session cookie.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    // Destroy the authenticated session.
    session_destroy();

    // Start a fresh anonymous session.
    if (!session_start()) {
        throw new RuntimeException('Unable to start a new session.');
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Unable to regenerate session ID.');
    }

    // Generate a fresh anonymous-session CSRF token.
    rotate_csrf_token();
}