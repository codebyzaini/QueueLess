/**
 * QueueLess — Smart Campus Queue Management System
 * Client-Side Application
 *
 * Features:
 * - Accessible password visibility toggles
 * - Dismissible notification alerts
 * - Form submission protection
 * - Button loading feedback
 * - Dashboard polling integration
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /*
     * 1. Password Visibility Toggle
     */

    const toggleButtons = document.querySelectorAll('.password-toggle-btn');

    toggleButtons.forEach((button) => {

        button.addEventListener('click', () => {

            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';

            button.setAttribute('aria-pressed', String(isPassword));

            button.setAttribute(
                'aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );

            const eyeIcon = button.querySelector('.icon-eye');
            const eyeOffIcon = button.querySelector('.icon-eye-off');

            if (eyeIcon) {
                eyeIcon.style.display = isPassword ? 'none' : 'block';
            }

            if (eyeOffIcon) {
                eyeOffIcon.style.display = isPassword ? 'block' : 'none';
            }

        });

    });


    /*
     * 2. Dismissible Flash Notifications
     */

    const alerts = document.querySelectorAll('.alert');

    alerts.forEach((alert) => {

        const closeButton = alert.querySelector(
            '.alert-close, [data-dismiss="alert"]'
        );

        if (!closeButton) {
            return;
        }

        closeButton.addEventListener('click', () => {

            alert.classList.add('alert-dismissed');

            window.setTimeout(() => {
                alert.remove();
            }, 200);

        });

    });


    /*
     * 3. Prevent Accidental Double Form Submission
     *
     * Forms using class="no-disable" are excluded.
     */

    const forms = document.querySelectorAll(
        'form:not(.no-disable)'
    );

    forms.forEach((form) => {

        form.addEventListener('submit', (event) => {

            // Do not interfere with forms that have already been submitted.
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            const submitButton = form.querySelector(
                'button[type="submit"], input[type="submit"]'
            );

            if (!submitButton || submitButton.disabled) {
                return;
            }

            form.dataset.submitting = 'true';

            submitButton.classList.add('btn-loading');

            submitButton.setAttribute('aria-busy', 'true');

            // Disable only after the browser has begun processing submission.
            window.setTimeout(() => {
                submitButton.disabled = true;
            }, 100);

        });

    });


    /*
     * 4. Dashboard Polling Configuration
     *
     * Actual polling will be enabled when the secure PHP
     * dashboard endpoint is created.
     */

    const dashboard = document.getElementById('student-active-ticket');

    if (dashboard) {

        const pollingInterval = 5000;

        dashboard.dataset.pollingInterval = String(pollingInterval);

        // Polling is intentionally not started yet.
        // It will be connected to the authenticated PHP endpoint
        // in a later development step.

    }

});