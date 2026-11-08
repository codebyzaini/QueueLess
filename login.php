<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Secure Authentication for Students, Staff and Admins
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

/**
 * Send authenticated users to their correct portal.
 */
$redirectByRole = static function (array $user): never {
    $destination = match ($user['role'] ?? 'student') {
        'admin' => 'admin/index.php',
        'staff' => 'staff/index.php',
        default => 'home.php',
    };

    redirect($destination);
};

if (is_logged_in()) {
    $redirectByRole(current_user());
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token.
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Your security session expired. Please reload the page and try again.';
    }

    // Collect submitted credentials.
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Validate email.
    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Validate password.
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    // Authenticate only after validation succeeds.
    if (empty($errors)) {

        $pdo = get_db_connection();

        if ($pdo === null) {

            $errors[] = 'The database service is currently unavailable. Please try again later.';

        } else {

            try {

                $stmt = $pdo->prepare(
                    'SELECT id, name, email, password_hash, role
                     FROM users
                     WHERE email = ?
                     LIMIT 1'
                );

                $stmt->execute([$email]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    $user !== false &&
                    password_verify($password, (string) $user['password_hash'])
                ) {

                    // Regenerate session and establish authenticated identity.
                    login_user($user);

                    set_flash(
                        'success',
                        'Welcome back, ' . $user['name'] . '!'
                    );

                    $redirectByRole($user);

                } else {

                    // Keep the message generic to prevent account enumeration.
                    $errors[] = 'Invalid email address or password. Please check your credentials.';

                }

            } catch (PDOException $e) {

                error_log('[QueueLess] Login error: ' . $e->getMessage());

                $errors[] = 'An unexpected system error occurred. Please try again later.';

            }

        }

    }

}

$pageTitle = 'Sign In';
$activeNav = 'login';

require_once __DIR__ . '/includes/header.php';
?>

<section class="container auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">

            <div class="hero-badge-pill">
                <span class="pill-dot" aria-hidden="true"></span>
                <span>Secure Campus Access</span>
            </div>

            <h1 class="auth-title">
                Welcome back
            </h1>

            <p class="auth-subtitle">
                Sign in to access your QueueLess account and campus services.
            </p>

        </div>

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger" role="alert">

                <div class="alert-content">

                    <ul style="margin: 0; padding-left: 1.25rem;">

                        <?php foreach ($errors as $error): ?>

                            <li><?= e($error) ?></li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            </div>

        <?php endif; ?>

        <form action="login.php"
              method="POST"
              id="login-form">

            <?= csrf_field() ?>

            <div class="form-group">

                <label for="email" class="form-label">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@university.edu"
                    value="<?= e($email) ?>"
                    required
                    maxlength="254"
                    autocomplete="username"
                    autofocus
                >

            </div>

            <div class="form-group">

                <label for="password" class="form-label">
                    Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="password-toggle-btn"
                        data-target="password"
                        aria-label="Show password"
                        aria-pressed="false"
                    >

                        <svg
                            class="toggle-icon icon-eye"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>

                        <svg
                            class="toggle-icon icon-eye-off"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                            style="display: none;"
                        >
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>

                    </button>

                </div>

            </div>

            <button
                type="submit"
                class="btn btn-primary btn-block"
                id="login-submit-btn"
            >
                Sign In to QueueLess
            </button>

        </form>

        <div class="auth-footer">

            Don't have an account yet?

            <a href="register.php" class="auth-link">
                Create a student account
            </a>

        </div>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>