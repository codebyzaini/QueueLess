<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Authentication & Session Management Module
 *
 * Implements secure authentication lifecycle:
 * - Session state checks (is_logged_in, current_user)
 * - Route guards (require_login, require_guest)
 * - Anti-session fixation session regeneration
 * - Clean, RFC-compliant session invalidation and cookie expiration
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

/**
 * Determine whether a user is currently authenticated in the active session.
 *
 * @return bool
 */
function is_logged_in(): bool {
    init_session();
    return !empty($_SESSION['user_id']);
}

/**
 * Retrieve metadata for the currently logged-in user.
 *
 * @return array{id: int, name: string, email: string, role: string}|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => (string)($_SESSION['user_name'] ?? 'User'),
        'email' => (string)($_SESSION['user_email'] ?? ''),
        'role'  => (string)($_SESSION['user_role'] ?? 'student')
    ];
}

/**
 * Route protection guard: Enforce authentication.
 * Redirects unauthenticated users to the login page.
 *
 * @param string $redirectTo
 * @return void
 */
function require_login(string $redirectTo = 'login.php'): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access your student portal.');
        redirect($redirectTo);
    }
}

/**
 * Route protection guard: Enforce a specific authenticated role.
 *
 * @param string $role
 * @return void
 */
function require_role(string $role): void {
    $user = current_user();

    if ($user === null || $user['role'] !== $role) {
        http_response_code(403);
        exit('Access denied.');
    }
}

/**
 * Route protection guard: Enforce authenticated student access.
 *
 * @return void
 */
function require_student(): void {
    require_role('student');
}

/**
 * Route protection guard: Enforce guest status.
 * Redirects already authenticated users to the home dashboard.
 *
 * @param string $redirectTo
 * @return void
 */
function require_guest(string $redirectTo = 'home.php'): void {
    if (is_logged_in()) {
        redirect($redirectTo);
    }
}

/**
 * Establish an authenticated user session.
 * Regenerates the session ID to thwart session fixation attacks
 * and rotates the synchronizer CSRF token across the privilege boundary.
 *
 * @param array{id: int|string, name: string, email: string, role: string} $user
 * @return void
 */
function login_user(array $user): void {
    init_session();

    // Prevent Session Fixation attack: regenerate ID and discard old session ID
    session_regenerate_id(true);

    // Rotate CSRF token across authentication boundary
    rotate_csrf_token();

    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_role']  = (string)$user['role'];
}

/**
 * Safely terminate the authenticated session while maintaining a clean
 * anonymous session for downstream flash notifications.
 *
 * - Wipes all authenticated user data from the session superglobal.
 * - Regenerates the session ID and deletes the old session store file on disk.
 * - Rotates the CSRF token to prevent session or token fixation.
 * - Leaves a clean anonymous session active so flash notifications survive redirect.
 *
 * @return void
 */
function logout_user(): void {
    init_session();

    // 1. Unset all session variables (removes all identity and credentials)
    $_SESSION = [];

    // 2. Expire the browser cookie for the current session identifier
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

    // 3. Destroy the old session file on disk
    session_destroy();

    // 4. Start a fresh anonymous session so flash notifications survive redirect
    session_start();
    session_regenerate_id(true);

    // 5. Issue a fresh CSRF token for the new anonymous session
    rotate_csrf_token();
}
