<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Student Authentication (Login)
 *
 * Authenticates users using prepared statements and password_verify().
 * Protects against CSRF, credential brute-forcing information leakage,
 * and session fixation.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Redirect authenticated users directly to the home dashboard
require_guest('home.php');

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Security token expired or invalid. Please reload the page and try again.';
    }

    // 2. Collect and sanitize input
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address format.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    // 3. Process Authentication if no input validation errors
    if (empty($errors)) {
        $pdo = get_db_connection();

        if ($pdo === null) {
            $errors[] = 'Database service is currently unreachable. Please verify server status in docs/SETUP_UBUNTU.md.';
        } else {
            try {
                // Prepared statement ensures immunity against SQL injection
                $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, (string)$user['password_hash'])) {
                    // Successful authentication: regenerate session & establish identity
                    login_user($user);
                    set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                    redirect('home.php');
                } else {
                    // Generic error message prevents account enumeration
                    $errors[] = 'Invalid email address or password. Please verify your credentials.';
                }
            } catch (PDOException $e) {
                error_log('[QueueLess] Login query error: ' . $e->getMessage());
                $errors[] = 'An internal system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Student Sign In';
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Student Portal Sign In</h1>
            <p class="auth-subtitle">Access virtual queues and monitor department counters</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul style="margin: 0; padding-left: 1.25rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" novalidate id="login-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email" class="form-label">Campus Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="student@university.edu" 
                    value="<?= e($email) ?>" 
                    required 
                    autocomplete="email"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="••••••••" 
                        required 
                        autocomplete="current-password"
                    >
                    <button 
                        type="button" 
                        class="password-toggle-btn" 
                        data-target="password" 
                        aria-label="Toggle password visibility"
                        aria-pressed="false"
                    >
                        <svg class="toggle-icon icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="toggle-icon icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="login-submit-btn">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account yet? 
            <a href="register.php" class="auth-link">Create student account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
