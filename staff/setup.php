<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    exit('Database connection failed.');
}

$pageTitle = 'Set Up Staff Password';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;
$staffName = '';

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    $error = 'This password setup link is invalid.';
} else {
    $tokenHash = hash('sha256', $token);

    $lookup = $pdo->prepare(
        "SELECT t.id, t.user_id, u.name
         FROM staff_setup_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ?
           AND t.used_at IS NULL
           AND t.expires_at > NOW()
           AND u.role = 'staff'
         LIMIT 1"
    );

    $lookup->execute([$tokenHash]);
    $setup = $lookup->fetch();

    if (!$setup) {
        http_response_code(400);
        $error = 'This setup link has expired, already been used, or is invalid.';
    } else {
        $staffName = $setup['name'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
                $error = 'Your session has expired. Refresh the page and try again.';
            } else {
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (strlen($password) < 12) {
                    $error = 'Your password must contain at least 12 characters.';
                } elseif (strlen($password) > 4096) {
                    $error = 'Your password is too long.';
                } elseif ($password !== $confirmPassword) {
                    $error = 'The passwords do not match.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $lock = $pdo->prepare(
                            "SELECT t.id, t.user_id
                             FROM staff_setup_tokens t
                             INNER JOIN users u ON u.id = t.user_id
                             WHERE t.token_hash = ?
                               AND t.used_at IS NULL
                               AND t.expires_at > NOW()
                               AND u.role = 'staff'
                             FOR UPDATE"
                        );

                        $lock->execute([$tokenHash]);
                        $lockedToken = $lock->fetch();

                        if (!$lockedToken) {
                            throw new RuntimeException(
                                'This setup link is no longer valid.'
                            );
                        }

                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $updateUser = $pdo->prepare(
                            "UPDATE users
                             SET password_hash = ?
                             WHERE id = ? AND role = 'staff'"
                        );

                        $updateUser->execute([
                            $passwordHash,
                            $lockedToken['user_id']
                        ]);

                        $markUsed = $pdo->prepare(
                            "UPDATE staff_setup_tokens
                             SET used_at = NOW()
                             WHERE id = ? AND used_at IS NULL"
                        );

                        $markUsed->execute([$lockedToken['id']]);

                        if ($markUsed->rowCount() !== 1) {
                            throw new RuntimeException(
                                'Unable to complete password setup.'
                            );
                        }

                        // Invalidate any other outstanding setup links.
                        $invalidate = $pdo->prepare(
                            "UPDATE staff_setup_tokens
                             SET used_at = NOW()
                             WHERE user_id = ?
                               AND used_at IS NULL"
                        );

                        $invalidate->execute([
                            $lockedToken['user_id']
                        ]);

                        $pdo->commit();

                        $success = true;
                        $error = '';

                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        error_log(
                            'Staff password setup error: ' .
                            $exception->getMessage()
                        );

                        $error = $exception instanceof RuntimeException
                            ? $exception->getMessage()
                            : 'Unable to set your password. Please try again.';
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="auth-wrapper">

    <section class="auth-card">

        <div class="auth-header">
            <span class="hero-badge-pill">STAFF ACCOUNT</span>

            <h1 class="auth-title">
                Set Your Password
            </h1>

            <p class="auth-subtitle">
                Welcome<?= $staffName !== '' ? ', ' . e($staffName) : '' ?>!
                Create a secure password to activate your QueueLess staff account.
            </p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>

            <div class="alert alert-success">
                Your password has been created successfully.
                You can now log in to your staff account.
            </div>

            <a href="/queueless/login.php" class="btn btn-primary btn-block">
                Go to Login
            </a>

        <?php elseif ($staffName !== '' && isset($setup)): ?>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="token"
                    value="<?= e($token) ?>"
                >

                <div class="form-group">
                    <label class="form-label" for="password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        minlength="12"
                        maxlength="4096"
                        autocomplete="new-password"
                        placeholder="At least 12 characters"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-control"
                        minlength="12"
                        maxlength="4096"
                        autocomplete="new-password"
                        placeholder="Enter your password again"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Activate Staff Account
                </button>
            </form>

        <?php endif; ?>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>