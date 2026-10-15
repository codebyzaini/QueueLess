-- ============================================================================
-- QueueLess — Smart Campus Queue Management System
-- Database Seed Data (Milestone 1)
--
-- Inserts realistic sample campus services for demonstration and testing.
-- Note: Contains zero credentials, zero passwords, and zero personal information.
-- Students register their own accounts through register.php.
-- ============================================================================

-- Idempotent Insertion Strategy:
-- Uses `INSERT IGNORE` so this seed script can be run repeatedly without
-- throwing duplicate key errors (Error 1062 on `uq_services_name`).
--
-- Why INSERT IGNORE is used instead of TRUNCATE:
-- 1. `TRUNCATE TABLE services` fails in InnoDB when foreign key constraints exist
--    (from `queue_tickets.service_id`), even if the tickets table is empty.
-- 2. Preserves existing service record IDs so any associated ticket history remains intact.
-- 3. Idempotent: Executing this script multiple times safely guarantees that all 5
--    core departments exist without creating duplicate rows or erroring out.
-- ============================================================================

INSERT IGNORE INTO `services` (`name`, `description`, `staff_id`, `is_open`, `created_at`) VALUES
(
    'Registrar''s Office',
    'Official academic transcripts, graduation audits, enrollment verifications, and major declarations.',
    NULL,
    1,
    NOW()
),
(
    'Financial Aid & Student Accounts',
    'Scholarship disbursements, tuition payment inquiries, fee adjustments, and student loan counseling.',
    NULL,
    1,
    NOW()
),
(
    'Academic Advising Center',
    'Undergraduate course planning, prerequisite waivers, academic probation support, and degree progress checks.',
    NULL,
    1,
    NOW()
),
(
    'Campus ID & Access Card Services',
    'New student ID card issuance, door access permissions, and lost card replacements.',
    NULL,
    1,
    NOW()
),
(
    'Campus IT Helpdesk & Hardware Support',
    'Campus Wi-Fi configuration, university portal password resets, and student laptop diagnostics.',
    NULL,
    0, -- Marked closed to demonstrate the 'Closed' status badge in student dashboard
    NOW()
);
