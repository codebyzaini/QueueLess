/**
 * QueueLess — Smart Campus Queue Management System
 * Client-Side Vanilla JavaScript
 *
 * Provides progressive enhancements:
 * - Accessible password visibility toggle
 * - Alert dismissal
 * - Form submission state feedback
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Password Visibility Toggle
    const toggleButtons = document.querySelectorAll('.password-toggle-btn');

    toggleButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);

            if (!input) return;

            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');

            // Update ARIA accessibility attribute
            btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

            // Toggle SVG icons
            const eyeIcon = btn.querySelector('.icon-eye');
            const eyeOffIcon = btn.querySelector('.icon-eye-off');

            if (eyeIcon && eyeOffIcon) {
                if (isPassword) {
                    eyeIcon.style.display = 'none';
                    eyeOffIcon.style.display = 'block';
                } else {
                    eyeIcon.style.display = 'block';
                    eyeOffIcon.style.display = 'none';
                }
            }
        });
    });

    // 2. Prevent Double Form Submission
    const forms = document.querySelectorAll('form:not(.no-disable)');
    forms.forEach((form) => {
        form.addEventListener('submit', (e) => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                // Short timeout to allow browser submit cycle to fire
                setTimeout(() => {
                    submitBtn.classList.add('btn-loading');
                }, 50);
            }
        });
    });
});
