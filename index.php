<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Public Landing Page
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Route authenticated users to their appropriate portal.
if (is_logged_in()) {
    $user = current_user();

    $destination = match ($user['role'] ?? 'student') {
        'admin' => 'admin/index.php',
        'staff' => 'staff/index.php',
        default => 'home.php',
    };

    redirect($destination);
}

$pageTitle = 'Smart Campus Queue Management';
$activeNav = 'index';

require_once __DIR__ . '/includes/header.php';
?>

<section class="container hero-section">
    <div class="hero-grid">

        <div class="hero-content">

            <div class="hero-badge-pill">
                <span class="pill-dot" aria-hidden="true"></span>
                <span>Smarter Campus Service Experience</span>
            </div>

            <h1 class="hero-title">
                Your time matters.
                <span class="brand-accent">Your queue should too.</span>
            </h1>

            <p class="hero-description">
                Skip unnecessary campus waiting lines with QueueLess.
                Join university service queues online, keep track of your
                position, and know when it is your turn — all from one
                convenient student portal.
            </p>

            <div class="hero-actions">

                <a href="register.php"
                   class="btn btn-lg btn-primary"
                   id="hero-get-started-btn">
                    Get Started
                </a>

                <a href="login.php"
                   class="btn btn-lg btn-outline"
                   id="hero-login-btn">
                    Sign In
                </a>

            </div>

            <p class="form-text">
                Simple queue management for a more organized campus.
            </p>

        </div>

        <div class="hero-preview-col">

            <div class="ticket-preview-card"
                 aria-label="Illustrative virtual queue ticket preview">

                <div class="ticket-header">

                    <span class="ticket-header-title">
                        Student Service
                    </span>

                    <span class="ticket-header-pill">
                        Queue Preview
                    </span>

                </div>

                <div class="ticket-body">

                    <div class="token-label">
                        YOUR SAMPLE TOKEN
                    </div>

                    <div class="token-large">
                        A-024
                    </div>

                    <div class="ticket-metrics">

                        <div class="metric-item">
                            <div class="metric-val">03</div>
                            <div class="metric-label">People Ahead</div>
                        </div>

                        <div class="metric-item">
                            <div class="metric-val">12 min</div>
                            <div class="metric-label">Estimated Wait</div>
                        </div>

                    </div>

                    <p class="form-text">
                        Example status:
                        <strong>Waiting in queue</strong>
                    </p>

                </div>

                <div class="ticket-notice-banner">
                    Illustrative preview — not live queue data
                </div>

            </div>

        </div>

    </div>
</section>

<section class="features-section">

    <div class="container">

        <div class="section-header">

            <h2 class="section-title">
                A better way to access campus services
            </h2>

            <p class="section-subtitle">
                Spend less time waiting and more time focusing on what
                matters. QueueLess makes university service queues easier
                to access and manage.
            </p>

        </div>

        <div class="steps-grid">

            <article class="step-card">

                <div class="step-number">01</div>

                <h3 class="step-title">
                    Choose a Service
                </h3>

                <p class="step-text">
                    Browse available university services, including
                    Admissions, Accounts, Examinations, Student Affairs,
                    and IT Support.
                </p>

            </article>

            <article class="step-card">

                <div class="step-number">02</div>

                <h3 class="step-title">
                    Join the Queue
                </h3>

                <p class="step-text">
                    Receive a unique queue token and reserve your place
                    without having to stand in a physical line.
                </p>

            </article>

            <article class="step-card">

                <div class="step-number">03</div>

                <h3 class="step-title">
                    Track Your Turn
                </h3>

                <p class="step-text">
                    Follow your queue status through your student portal
                    and visit the service counter when your turn arrives.
                </p>

            </article>

        </div>

    </div>

</section>

<section class="features-section">

    <div class="container">

        <div class="section-header">

            <h2 class="section-title">
                Campus services, one platform
            </h2>

            <p class="section-subtitle">
                Access essential university departments through a
                centralized queue management experience.
            </p>

        </div>

        <div class="steps-grid">

            <article class="step-card">
                <h3 class="step-title">Admissions Office</h3>
                <p class="step-text">
                    Manage admission-related enquiries and applications.
                </p>
            </article>

            <article class="step-card">
                <h3 class="step-title">Accounts Office</h3>
                <p class="step-text">
                    Access fee and account-related university services.
                </p>
            </article>

            <article class="step-card">
                <h3 class="step-title">Examination Office</h3>
                <p class="step-text">
                    Get assistance with examination-related matters.
                </p>
            </article>

            <article class="step-card">
                <h3 class="step-title">Student Affairs</h3>
                <p class="step-text">
                    Reach student support and administrative services.
                </p>
            </article>

            <article class="step-card">
                <h3 class="step-title">IT Support</h3>
                <p class="step-text">
                    Request help with campus technology and IT services.
                </p>
            </article>

        </div>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>