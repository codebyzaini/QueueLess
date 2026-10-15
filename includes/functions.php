<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Core Helper Functions
 * 
 * Provides safe session initialization, HTML escaping,
 * HTTP redirection, and one-time session flash notifications.
 */

// Declare strict types for defensive engineering
declare(strict_types=1);

/**
 * Initialize a secure session if not already started.
 * Configures cookie security parameters compliant with OWASP recommendations:
 * - HttpOnly: Prevents client-side script access to session cookie.
 * - SameSite=Lax: Mitigates Cross-Site Request Forgery (CSRF).
 * - Secure: Automatically enabled if running over HTTPS.
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,              // Session expires when browser closes
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,       // Enabled on HTTPS, works seamlessly on local HTTP
            'httponly' => true,           // Inaccessible via JavaScript document.cookie
            'samesite' => 'Lax'           // Cross-site request protection
        ]);

        session_start();
    }
}

/**
 * Safely escape string for HTML output.
 * Prevents Cross-Site Scripting (XSS).
 *
 * @param string|null $value
 * @return string
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Safely redirect to a relative path and terminate script execution.
 *
 * @param string $path
 * @return void
 */
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

/**
 * Store a flash message to be displayed on the next page view.
 *
 * @param string $type 'success', 'danger', 'warning', or 'info'
 * @param string $message
 * @return void
 */
function set_flash(string $type, string $message): void {
    init_session();
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear all queued flash messages.
 *
 * @return array<int, array{type: string, message: string}>
 */
function get_flashes(): array {
    init_session();
    if (empty($_SESSION['flash_messages'])) {
        return [];
    }
    $messages = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
    return $messages;
}
