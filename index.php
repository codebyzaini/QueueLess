<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Public Landing Page
 *
 * Welcomes students, outlines the queue system benefits, and directs
 * unauthenticated visitors to register or sign in. Redirects active
 * sessions directly to the student dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect authenticated users immediately to their student dashboard
if (is_logged_in()) {
    redirect('home.php');
}

$pageTitle = 'Campus Queue Management';
$activeNav = 'index';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container hero-section">
    <div class="hero-grid">
        <div class="hero-content">
            <div class="hero-badge-pill">
                <span class="pill-dot"></span>
                <span>University Queue Operations • Milestone 1</span>
            </div>
            <h1 class="hero-title">Skip the campus waiting line. Manage your time.</h1>
            <p class="hero-description">
                QueueLess replaces crowded administrative hallways with an efficient, transparent virtual queue. Reserve your service token online, track queue availability, and arrive at department counters precisely when you are called.
            </p>
            <div class="hero-actions">
                <a href="register.php" class="btn btn-lg btn-primary" id="hero-get-started-btn">
                    Create Student Account
                </a>
                <a href="login.php" class="btn btn-lg btn-outline" id="hero-login-btn">
                    Sign In to Portal
                </a>
            </div>
        </div>

        <div class="hero-preview-col">
            <!-- Illustrative Ticket Preview (Explicitly labeled as Sample Data) -->
            <div class="ticket-preview-card" aria-label="Sample Queue Ticket Preview">
                <div class="ticket-header">
                    <span class="ticket-header-title">Registrar's Office</span>
                    <span class="ticket-header-pill">Counter 02</span>
                </div>
                <div class="ticket-body">
                    <div class="token-large">R-104</div>
                    <div class="token-label">Virtual Token Number</div>
                    <div class="ticket-metrics">
                        <div class="metric-item">
                            <div class="metric-val">3</div>
                            <div class="metric-label">Ahead of You</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-val">~12 min</div>
                            <div class="metric-label">Est. Wait Time</div>
                        </div>
                    </div>
                    <p class="form-text">
                        Status: <strong>Serving R-101</strong>
                    </p>
                </div>
                <div class="ticket-notice-banner">
                    Illustrative Preview — Sample Data Only
                </div>
            </div>
        </div>
    </div>
</div>

<section class="features-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">How Virtual Queueing Works</h2>
            <p class="section-subtitle">A straightforward three-step workflow designed for student convenience and department efficiency.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3 class="step-title">Select Campus Department</h3>
                <p class="step-text">
                    Log in to your authenticated student dashboard to view real-time department counter statuses, including the Registrar, Financial Aid, and Academic Advising.
                </p>
            </div>

            <div class="step-card">
                <div class="step-number">2</div>
                <h3 class="step-title">Receive Your Token</h3>
                <p class="step-text">
                    Reserve your position virtually without standing in physical hallways. Your secure sequential token guarantees your spot in line.
                </p>
            </div>

            <div class="step-card">
                <div class="step-number">3</div>
                <h3 class="step-title">Arrive When Called</h3>
                <p class="step-text">
                    Spend your waiting time studying or relaxing. Head directly to the campus counter once your token is called by university staff.
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
