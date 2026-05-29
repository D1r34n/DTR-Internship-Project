-- ============================================================
-- MIGRATION: Separate log_edit_requests and schedule_edit_requests
-- Run this ONCE against the existing database.
-- Safe to run again (uses IF NOT EXISTS / IF EXISTS guards).
-- ============================================================

SET foreign_key_checks = 0;

-- ─────────────────────────────────────────────────────────────
-- 1. Create schedule_edit_requests
--    One row per batch_id — owns status and requester info.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `schedule_edit_requests` (
  `id`           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id`     varchar(32)         NOT NULL,
  `employee_id`  bigint(20)          NOT NULL,
  `requested_by` int(11)             NOT NULL,
  `status`       enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ser_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Populate from existing schedules batch rows (MIN per batch since all rows
-- in a batch share the same employee_id / requested_by / status).
INSERT IGNORE INTO schedule_edit_requests (batch_id, employee_id, requested_by, status)
SELECT
    s.batch_id,
    MIN(s.employee_id)                       AS employee_id,
    MIN(COALESCE(s.requested_by, s.employee_id)) AS requested_by,
    MIN(s.status)                            AS status
FROM schedules s
WHERE s.batch_id IS NOT NULL
GROUP BY s.batch_id;

-- ─────────────────────────────────────────────────────────────
-- 2. Add schedule_request_id to logs (links ADD/EDIT_SCHEDULE
--    log entries to their schedule_edit_requests row).
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `logs`
    ADD COLUMN IF NOT EXISTS `schedule_request_id` bigint(20) UNSIGNED DEFAULT NULL
    AFTER `original_log_time`;

-- Wire up existing ADD_SCHEDULE / EDIT_SCHEDULE log entries.
-- edit_reason currently stores the batch_id for these rows.
UPDATE `logs` l
JOIN   `schedule_edit_requests` ser ON ser.batch_id = l.edit_reason
SET    l.schedule_request_id = ser.id
WHERE  l.log_type IN ('ADD_SCHEDULE', 'EDIT_SCHEDULE')
  AND  l.schedule_request_id IS NULL;

-- ─────────────────────────────────────────────────────────────
-- 3. Replace old log_edit_requests with new structure
-- ─────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `log_edit_requests`;

CREATE TABLE `log_edit_requests` (
  `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_id`             bigint(20)          NOT NULL,
  `employee_id`        bigint(20)          NOT NULL,
  `original_log_time`  datetime            NOT NULL,
  `proposed_log_time`  datetime            NOT NULL,
  `reason`             text                NOT NULL,
  `requested_by`       int(11)             NOT NULL,
  `status`             enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`         timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ler_log_id`      (`log_id`),
  KEY `idx_ler_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrate existing log-edit data from the logs table.
-- Applies only to clock-log entries (IN/OUT/BREAK_*) that have edit_status set.
INSERT INTO log_edit_requests
    (log_id, employee_id, original_log_time, proposed_log_time, reason, requested_by, status, created_at)
SELECT
    l.id,
    l.employee_id,
    COALESCE(l.original_log_time, l.log_time)           AS original_log_time,
    COALESCE(l.proposed_log_time, l.log_time)           AS proposed_log_time,
    COALESCE(l.edit_reason, 'No reason provided')       AS reason,
    COALESCE(l.edit_requested_by, l.employee_id)        AS requested_by,
    l.edit_status                                        AS status,
    l.created_at
FROM logs l
WHERE l.edit_status IS NOT NULL
  AND (l.log_type NOT IN ('ADD_EMPLOYEE','EDIT_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE')
       OR l.log_type IS NULL);

-- ─────────────────────────────────────────────────────────────
-- 4. Strip request-flow columns from logs
--    Keep edit_reason + edit_requested_by — still used by
--    ADD_EMPLOYEE / EDIT_EMPLOYEE / ADD_SCHEDULE / EDIT_SCHEDULE
--    log entries for actor and diff tracking.
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `logs`
    DROP COLUMN IF EXISTS `proposed_log_time`,
    DROP COLUMN IF EXISTS `edit_status`;

-- ─────────────────────────────────────────────────────────────
-- 5. Strip request columns from schedules
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `schedules`
    DROP COLUMN IF EXISTS `status`,
    DROP COLUMN IF EXISTS `requested_by`;

SET foreign_key_checks = 1;
