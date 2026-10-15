<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Shared Page Header & Navigation
 *
 * Provides semantic HTML5 boilerplate, accessible navigation bar,
 * offline styling integration, and global flash message alerting.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

$user = current_user();
$title = isset($pageTitle) ? e($pageTitle) . ' | QueueLess' : 'QueueLess — Smart Campus Queue Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <!-- Offline System Typography & Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="app-header">
        <div class="container header-container">
            <a href="<?= is_logged_in() ? 'home.php' : 'index.php' ?>" class="brand-logo" aria-label="QueueLess Home">
                <svg class="brand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
                <span class="brand-text">Queue<span class="brand-accent">Less</span></span>
                <span class="brand-badge">Campus</span>
            </a>

            <nav class="main-nav" aria-label="Primary Navigation">
                <ul class="nav-links">
                    <?php if (is_logged_in()): ?>
                        <li><a href="home.php" class="nav-link <?= (isset($activeNav) && $activeNav === 'home') ? 'active' : '' ?>">Student Portal</a></li>
                        <li class="user-greeting">
                            <span class="avatar-pill">
                                <svg class="avatar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span class="user-name"><?= e($user['name']) ?></span>
                            </span>
                        </li>
                        <li>
                            <form action="logout.php" method="POST" class="inline-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" id="nav-logout-btn">
                                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    Log Out
                                </button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li><a href="index.php" class="nav-link <?= (isset($activeNav) && $activeNav === 'index') ? 'active' : '' ?>">Overview</a></li>
                        <li><a href="login.php" class="btn btn-sm btn-outline <?= (isset($activeNav) && $activeNav === 'login') ? 'active' : '' ?>" id="nav-login-btn">Sign In</a></li>
                        <li><a href="register.php" class="btn btn-sm btn-primary <?= (isset($activeNav) && $activeNav === 'register') ? 'active' : '' ?>" id="nav-register-btn">Get Started</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="app-main">
        <div class="container alerts-container">
            <?php
            $flashList = get_flashes();
            if (!empty($flashList)):
                foreach ($flashList as $flash):
                    $typeClass = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
            ?>
                <div class="alert alert-<?= $typeClass ?>" role="alert">
                    <div class="alert-content">
                        <span class="alert-message"><?= e($flash['message']) ?></span>
                    </div>
                    <button type="button" class="alert-close" aria-label="Close message" onclick="this.parentElement.remove();">&times;</button>
                </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>
