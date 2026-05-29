-- ============================================================
-- MIGRATION: Add approved_by and updated_at to request tables
-- Run this ONCE against the existing database.
-- Safe to run again (uses IF NOT EXISTS guards).
-- ============================================================

-- employee_leave_balances: support decimal vacation accrual + total allocation columns
ALTER TABLE `employee_leave_balances`
    MODIFY COLUMN `vacation_leave` decimal(5,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `total_vacation_leave` decimal(5,2) NOT NULL DEFAULT 0.00 AFTER `vacation_leave`,
    ADD COLUMN IF NOT EXISTS `total_buffer_leave`   int(11)      NOT NULL DEFAULT 0     AFTER `buffer_leave`;

-- leave_requests: add missing client_name + resolution tracking
ALTER TABLE `leave_requests`
    ADD COLUMN IF NOT EXISTS `client_name` varchar(255) DEFAULT NULL AFTER `reason`,
    ADD COLUMN IF NOT EXISTS `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL AFTER `updated_at`;

-- overtime_requests: add resolution tracking
ALTER TABLE `overtime_requests`
    ADD COLUMN IF NOT EXISTS `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL AFTER `updated_at`;

-- log_edit_requests: add who approved (requested_by already tracks who filed)
ALTER TABLE `log_edit_requests`
    ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL AFTER `updated_at`;

-- schedule_edit_requests: add who approved
ALTER TABLE `schedule_edit_requests`
    ADD COLUMN IF NOT EXISTS `approved_by` int(11) DEFAULT NULL AFTER `updated_at`;
