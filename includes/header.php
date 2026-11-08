<?php
declare(strict_types=1);

/**
 * QueueLess — Shared Page Header & Navigation
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

$user = current_user();

$pageTitle = isset($pageTitle) && is_string($pageTitle)
    ? $pageTitle
    : 'Smart Campus Queue Management';

$title = e($pageTitle . ' | QueueLess');

$role = $user['role'] ?? null;

$portalLabel = match ($role) {
    'admin' => 'Admin Portal',
    'staff' => 'Staff Portal',
    'student' => 'Student Portal',
    default => null,
};

$homeUrl = match ($role) {
    'admin' => '/queueless/admin/index.php',
    'staff' => '/queueless/staff/index.php',
    'student' => '/queueless/home.php',
    default => '/queueless/index.php',
};

$stylesheetPath = '/queueless/assets/css/style.css?v='
    . filemtime(__DIR__ . '/../assets/css/style.css');

$scriptPath = '/queueless/assets/js/script.js';

$logoFile = __DIR__ . '/../assets/images/logo.png';
$logoUrl = '/queueless/assets/images/logo.png';

$hasCustomLogo = is_file($logoFile);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#0b1220">
    <meta name="description" content="QueueLess — Smart Campus Queue Management System">

    <title><?= $title ?></title>

    <link rel="stylesheet" href="<?= e($stylesheetPath) ?>">
    <script src="<?= e($scriptPath) ?>" defer></script>

    <style>
        /* Shared QueueLess navigation */

        .ql-header {
            background: #0b1220;
            color: #ffffff;
            position: relative;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .ql-header-inner {
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        /* .ql-brand {
            display: inline-flex;
            align-items: center;
            gap: 13px;
            text-decoration: none;
            color: #ffffff;
            flex-shrink: 0;
            min-width: 0;
        } */
.ql-brand {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    flex-shrink: 0;
    min-width: 0;
}
        
    
.ql-brand-image {
    width: 220px;
    height: 76px;
    object-fit: contain;
    object-position: left center;
    display: block;
    flex-shrink: 0;
}
.ql-brand-image {
    background: #ffffff;
    border-radius: 8px;
    padding: 5px 10px;
    box-sizing: border-box;
}

        .ql-brand-name {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.6px;
            white-space: nowrap;
        }

        .ql-brand-name span {
            color: #4f91ff;
        }

        .ql-brand-badge {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            color: #a9caff;
            border: 1px solid rgba(79,145,255,0.35);
            background: rgba(79,145,255,0.10);
            padding: 5px 8px;
            border-radius: 5px;
        }

        .ql-nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 22px;
            min-width: 0;
        }

        .ql-portal-link {
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 650;
            white-space: nowrap;
        }

        .ql-portal-link:hover {
            color: #79aaff;
        }

        .ql-user {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            max-width: 220px;
        }

        .ql-avatar {
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(148,163,184,0.35);
            background: #17243a;
            color: #c5dcff;
            border-radius: 50%;
        }

        .ql-avatar svg {
            width: 19px;
            height: 19px;
        }

        .ql-user-name {
            color: #dce6f5;
            font-size: 13px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ql-logout-form {
            margin: 0;
            flex-shrink: 0;
        }

        .ql-logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid rgba(239,68,68,0.35);
            border-radius: 9px;
            background: transparent;
            color: #ff7777;
            font-size: 13px;
            font-weight: 650;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s ease;
        }

        .ql-logout-button:hover {
            background: rgba(239,68,68,0.10);
            border-color: #ef4444;
        }

        .ql-logout-button svg {
            width: 17px;
            height: 17px;
        }

        .ql-public-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ql-public-nav a {
            text-decoration: none;
        }

        @media (max-width: 760px) {
            .ql-header-inner {
                padding-top: 13px;
                padding-bottom: 13px;
                min-height: auto;
                flex-wrap: wrap;
                gap: 13px;
            }

            .ql-nav {
                width: 100%;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
            }

            .ql-user {
                max-width: 160px;
            }
        }

        @media (max-width: 420px) {
            .ql-brand-name {
                font-size: 19px;
            }

            .ql-brand-image {
                width: 48px;
                height: 48px;
            }

            .ql-brand-badge {
                display: none;
            }

            .ql-nav {
                gap: 9px;
            }

            .ql-portal-link {
                font-size: 12px;
            }

            .ql-user {
                max-width: 115px;
                gap: 6px;
            }

            .ql-avatar {
                width: 31px;
                height: 31px;
            }

            .ql-user-name {
                font-size: 11px;
            }

            .ql-logout-button {
                padding: 0 9px;
                min-height: 36px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

<header class="ql-header">
    <div class="container ql-header-inner">
<a href="<?= e($homeUrl) ?>"
   class="ql-brand"
   aria-label="QueueLess Home">

    <?php if ($hasCustomLogo): ?>

        <img
            src="<?= e($logoUrl) ?>"
            alt="QueueLess Campus Queue Management"
            class="ql-brand-image"
        >

    <?php else: ?>

        <span class="ql-brand-name">
            Queue<span>Less</span>
        </span>

        <span class="ql-brand-badge">Campus</span>

    <?php endif; ?>

</a>


        <nav class="ql-nav" aria-label="Primary navigation">

            <?php if (is_logged_in()): ?>

                <a href="<?= e($homeUrl) ?>" class="ql-portal-link">
                    <?= e($portalLabel ?? 'Dashboard') ?>
                </a>

                <div class="ql-user">

                    <span class="ql-avatar" aria-hidden="true">
                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M5 21v-2a7 7 0 0 1 14 0v2"></path>
                        </svg>
                    </span>

                    <span class="ql-user-name">
                        <?= e($user['name'] ?? 'User') ?>
                    </span>

                </div>

                <form action="/queueless/logout.php"
                      method="POST"
                      class="ql-logout-form">

                    <?= csrf_field() ?>

                    <button type="submit" class="ql-logout-button">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M10 17l5-5-5-5"></path>
                            <path d="M15 12H3"></path>
                            <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path>
                        </svg>

                        Log Out

                    </button>

                </form>

            <?php else: ?>

                <div class="ql-public-nav">

                    <a href="/queueless/index.php" class="nav-link">
                        Overview
                    </a>

                    <a href="/queueless/login.php"
                       class="btn btn-sm btn-outline"
                       id="nav-login-btn">
                        Sign In
                    </a>

                    <a href="/queueless/register.php"
                       class="btn btn-sm btn-primary"
                       id="nav-register-btn">
                        Get Started
                    </a>

                </div>

            <?php endif; ?>

        </nav>

    </div>
</header>

<main class="app-main">

    <div class="container alerts-container">

        <?php
        $flashList = get_flashes();

        foreach ($flashList as $flash):

            $typeClass = in_array(
                $flash['type'],
                ['success', 'danger', 'warning', 'info'],
                true
            ) ? $flash['type'] : 'info';
        ?>

            <div class="alert alert-<?= e($typeClass) ?>" role="alert">

                <div class="alert-content">
                    <span class="alert-message">
                        <?= e($flash['message']) ?>
                    </span>
                </div>

                <button
                    type="button"
                    class="alert-close"
                    aria-label="Close message"
                    onclick="this.parentElement.remove();">
                    &times;
                </button>

            </div>

        <?php endforeach; ?>

    </div>