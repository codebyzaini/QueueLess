<?php
/**
 * QueueLess — Smart Campus Queue Management System
 * Shared Page Footer
 *
 * Provides academic attribution, system architecture indicators,
 * and vanilla JavaScript asset integration.
 */

declare(strict_types=1);
?>
    </main>

    <footer class="app-footer">
        <div class="container footer-container">
            <div class="footer-brand">
                <div class="footer-logo">
                    <svg class="brand-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                    <strong>QueueLess</strong>
                </div>
                <p class="footer-desc">
                    Smart Campus Queue Management System. Engineered for high-throughput, secure, and transparent university service operations.
                </p>
            </div>

            <div class="footer-meta">
                <div class="system-badge">
                    <span class="status-dot"></span>
                    <span>Architecture: PHP 8.x • Apache 2.4 • MySQL 8.x (InnoDB)</span>
                </div>
                <p class="copyright-text">
                    &copy; <?= date('Y') ?> QueueLess Project. Academic Milestone 1 Submission.
                </p>
            </div>
        </div>
    </footer>

    <!-- Offline Vanilla JavaScript -->
    <script src="assets/js/script.js"></script>
</body>
</html>
