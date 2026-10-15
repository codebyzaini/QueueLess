<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Student Registration (Sign-Up)
 *
 * Validates registration input, enforces unique emails, hashes passwords,
 * and restricts public accounts strictly to the 'student' role.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Redirect authenticated users directly to their dashboard
require_guest('home.php');

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Security token expired or invalid. Please reload the page and try again.';
    }

    // 2. Collect and normalize inputs
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // Full Name Validation
    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters in length.';
    }

    // Email Validation
    if ($email === '') {
        $errors[] = 'Please enter your campus email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email format (e.g., student@university.edu).';
    } elseif (mb_strlen($email) > 191) {
        $errors[] = 'Email address cannot exceed 191 characters.';
    }

    // Password Validation
    if ($password === '') {
        $errors[] = 'Please create a secure password.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters in length.';
    }

    // Confirm Password Validation
    if ($password !== $confirmPassword) {
        $errors[] = 'The password and confirmation password do not match.';
    }

    // 3. Database Check & Insertion
    if (empty($errors)) {
        $pdo = get_db_connection();

        if ($pdo === null) {
            $errors[] = 'Database service is currently unreachable. Please verify server status in docs/SETUP_UBUNTU.md.';
        } else {
            try {
                // Check for existing duplicate email
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $checkStmt->execute([$email]);

                if ($checkStmt->fetch()) {
                    $errors[] = 'An account with this email address already exists. Please sign in or use another email.';
                } else {
                    // Hash password using default robust algorithm (Bcrypt / Argon2 depending on PHP runtime)
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    // Public registration strictly assigns role = 'student' (role elevation impossible)
                    $insertStmt = $pdo->prepare('
                        INSERT INTO users (name, email, password_hash, role, created_at)
                        VALUES (?, ?, ?, \'student\', NOW())
                    ');
                    $insertStmt->execute([$name, $email, $passwordHash]);

                    // Flash success notification and direct user to login
                    set_flash('success', 'Account created successfully! You can now sign in with your credentials.');
                    redirect('login.php');
                }
            } catch (PDOException $e) {
                error_log('[QueueLess] Registration DB error: ' . $e->getMessage());

                // Detect duplicate key constraint violation (SQLSTATE 23000 / MySQL error 1062)
                $sqlState = (string)$e->getCode();
                $driverCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : null;

                if ($sqlState === '23000' || $driverCode === 1062) {
                    $errors[] = 'An account with this email address already exists. Please sign in or use another email.';
                } else {
                    $errors[] = 'Unable to complete registration due to an internal system error. Please try again later.';
                }
            }
        }
    }
}

$pageTitle = 'Create Student Account';
$activeNav = 'register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Create Student Account</h1>
            <p class="auth-subtitle">Join QueueLess to access smart campus counter queues</p>
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

        <form action="register.php" method="POST" novalidate id="register-form">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name" class="form-label">Full Name</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control" 
                    placeholder="e.g. Jane Doe" 
                    value="<?= e($name) ?>" 
                    required 
                    autocomplete="name"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="email" class="form-label">University Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="student@university.edu" 
                    value="<?= e($email) ?>" 
                    required 
                    autocomplete="email"
                >
                <span class="form-text">Must be unique within the university queue system.</span>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Minimum 8 characters" 
                        required 
                        autocomplete="new-password"
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

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control" 
                        placeholder="Re-enter password" 
                        required 
                        autocomplete="new-password"
                    >
                    <button 
                        type="button" 
                        class="password-toggle-btn" 
                        data-target="confirm_password" 
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

            <button type="submit" class="btn btn-primary btn-block" id="register-submit-btn">
                Register Account
            </button>
        </form>

        <div class="auth-footer">
            Already registered? 
            <a href="login.php" class="auth-link">Sign in to your account</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
