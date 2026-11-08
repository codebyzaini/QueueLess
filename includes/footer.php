<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Shared Page Footer
 */

declare(strict_types=1);
?>

    </main>

    <footer class="app-footer">
        <div class="container footer-container">

            <div class="footer-brand">

                <div class="footer-logo">
                    <svg class="brand-icon-sm"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round"
                         aria-hidden="true">
                        <rect x="3" y="7" width="18" height="14" rx="2"></rect>
                        <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <path d="M3 12h18"></path>
                    </svg>

                    <strong>QueueLess</strong>
                </div>

                <p class="footer-desc">
                    Smart Campus Queue Management System.
                    Making university services more organized,
                    efficient, and accessible.
                </p>

            </div>

            <div class="footer-meta">

                <div class="system-badge">
                    <span class="status-dot" aria-hidden="true"></span>

                    <span>
                        PHP 8.x
                        <span aria-hidden="true">·</span>
                        Apache 2.4
                        <span aria-hidden="true">·</span>
                        MySQL 8.x
                    </span>
                </div>

                <p class="copyright-text">
                    &copy; <?= date('Y') ?> QueueLess.
                    Smart Campus Queue Management.
                </p>

            </div>

        </div>
    </footer>

</body>
</html>