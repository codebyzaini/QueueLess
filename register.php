<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Secure Student Registration
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/config/database.php';

// Public registration is restricted to student accounts.
if (is_logged_in()) {
    $user = current_user();

    $destination = match ($user['role'] ?? 'student') {
        'admin' => 'admin/index.php',
        'staff' => 'staff/index.php',
        default => 'home.php',
    };

    redirect($destination);
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token.
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Your security session expired. Please reload the page and try again.';
    }

    // Collect form data.
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    // Validate full name.
    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Your name must contain between 2 and 100 characters.';
    }

    // Validate email address.
    if ($email === '') {
        $errors[] = 'Please enter your university email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 191) {
        $errors[] = 'Your email address cannot exceed 191 characters.';
    }

    // Validate password.
    if ($password === '') {
        $errors[] = 'Please create a password.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Your password must contain at least 8 characters.';
    } elseif (strlen($password) > 72 && PASSWORD_DEFAULT === PASSWORD_BCRYPT) {
        $errors[] = 'Your password is too long. Please use a shorter password.';
    }

    // Confirm password.
    if ($password !== $confirmPassword) {
        $errors[] = 'Your passwords do not match. Please try again.';
    }

    // Create account when validation succeeds.
    if (empty($errors)) {

        $pdo = get_db_connection();

        if ($pdo === null) {

            $errors[] = 'The database service is currently unavailable. Please try again later.';

        } else {

            try {

                // Check whether the email address is already registered.
                $checkStmt = $pdo->prepare(
                    'SELECT id FROM users WHERE email = ? LIMIT 1'
                );

                $checkStmt->execute([$email]);

                if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {

                    $errors[] = 'An account with this email already exists. Please sign in or use another email.';

                } else {

                    // Securely hash the password.
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($passwordHash === false) {
                        throw new RuntimeException('Password hashing failed.');
                    }

                    // Always assign the student role.
                    // Never accept a role from public registration input.
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO users
                            (name, email, password_hash, role, created_at)
                         VALUES
                            (?, ?, ?, \'student\', NOW())'
                    );

                    $insertStmt->execute([
                        $name,
                        $email,
                        $passwordHash,
                    ]);

                    set_flash(
                        'success',
                        'Your student account has been created successfully. You can now sign in.'
                    );

                    redirect('login.php');
                }

            } catch (PDOException $e) {

                error_log('[QueueLess] Registration database error: ' . $e->getMessage());

                $sqlState = (string) $e->getCode();
                $driverCode = isset($e->errorInfo[1])
                    ? (int) $e->errorInfo[1]
                    : null;

                if ($sqlState === '23000' || $driverCode === 1062) {

                    $errors[] = 'An account with this email already exists. Please sign in or use another email.';

                } else {

                    $errors[] = 'Unable to complete registration right now. Please try again later.';

                }

            } catch (RuntimeException $e) {

                error_log('[QueueLess] Registration error: ' . $e->getMessage());

                $errors[] = 'Unable to create your account right now. Please try again later.';

            }

        }

    }

}

$pageTitle = 'Create Student Account';
$activeNav = 'register';

require_once __DIR__ . '/includes/header.php';
?>

<section class="container auth-wrapper">

    <div class="auth-card">

        <div class="auth-header">

            <div class="hero-badge-pill">
                <span class="pill-dot" aria-hidden="true"></span>
                <span>Join the QueueLess Campus Network</span>
            </div>

            <h1 class="auth-title">
                Create your account
            </h1>

            <p class="auth-subtitle">
                Register as a student to access university services
                and manage your queue online.
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

        <form action="register.php"
              method="POST"
              id="register-form">

            <?= csrf_field() ?>

            <div class="form-group">

                <label for="name" class="form-label">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    placeholder="Enter your full name"
                    value="<?= e($name) ?>"
                    required
                    minlength="2"
                    maxlength="100"
                    autocomplete="name"
                    autofocus
                >

            </div>

            <div class="form-group">

                <label for="email" class="form-label">
                    University Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@university.edu"
                    value="<?= e($email) ?>"
                    required
                    maxlength="191"
                    autocomplete="email"
                >

                <span class="form-text">
                    This email address will be used to sign in to your account.
                </span>

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
                        placeholder="At least 8 characters"
                        required
                        minlength="8"
                        autocomplete="new-password"
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

            <div class="form-group">

                <label for="confirm_password" class="form-label">
                    Confirm Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        placeholder="Enter your password again"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >

                    <button
                        type="button"
                        class="password-toggle-btn"
                        data-target="confirm_password"
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
                id="register-submit-btn"
            >
                Create Student Account
            </button>

        </form>

        <div class="auth-footer">

            Already have an account?

            <a href="login.php" class="auth-link">
                Sign in instead
            </a>

        </div>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>