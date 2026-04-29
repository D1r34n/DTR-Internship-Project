-- phpMyAdmin SQL Dump (Merged)
-- Merged from two exports dated Apr 29, 2026
-- Server version: 10.4.32-MariaDB

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Database: `dtr_system`
-- --------------------------------------------------------

-- --------------------------------------------------------
-- Table structure for table `attendances`
-- --------------------------------------------------------

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `schedule_id` bigint(20) UNSIGNED DEFAULT NULL,
  `work_date` date NOT NULL,
  `scheduled_start` datetime NOT NULL,
  `scheduled_end` datetime NOT NULL,
  `actual_time_in` datetime DEFAULT NULL,
  `actual_time_out` datetime DEFAULT NULL,
  `total_work_minutes` int(10) UNSIGNED DEFAULT 0,
  `late_minutes` int(10) UNSIGNED DEFAULT 0,
  `undertime_minutes` int(10) UNSIGNED DEFAULT 0,
  `overtime_minutes` int(10) UNSIGNED DEFAULT 0,
  `break_minutes` int(10) UNSIGNED DEFAULT 0,
  `status` enum('present','absent','incomplete') DEFAULT 'incomplete',
  `overtime_status` enum('none','pending','approved','rejected') DEFAULT 'none',
  `missed_time_out` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Merged data: union of both dumps (doc1 has rows 39, 40, 41 not in doc2)
INSERT INTO `attendances` (`id`, `employee_id`, `schedule_id`, `work_date`, `scheduled_start`, `scheduled_end`, `actual_time_in`, `actual_time_out`, `total_work_minutes`, `late_minutes`, `undertime_minutes`, `overtime_minutes`, `break_minutes`, `status`, `overtime_status`, `missed_time_out`, `created_at`, `updated_at`) VALUES
(20, 5, 74,   '2026-04-29', '2026-04-29 09:30:00', '2026-04-29 17:30:00', '2026-04-29 10:18:22', '2026-04-29 10:18:27', 0,   48,  432, 0,  0,  'present',    'none',     0, '2026-04-28 01:19:32', '2026-04-29 02:18:27'),
(21, 5, 75,   '2026-04-30', '2026-04-30 09:30:00', '2026-04-30 17:30:00', '2026-04-30 09:30:13', '2026-04-30 18:00:51', 9,   0,   0,   31, 60, 'present',    'none',     0, '2026-04-28 01:19:32', '2026-04-30 10:00:51'),
(22, 5, 76,   '2026-05-01', '2026-05-01 09:30:00', '2026-05-01 17:30:00', NULL,                  NULL,                  0,   0,   0,   0,  0,  'incomplete', 'none',     0, '2026-04-28 01:19:32', '2026-04-28 01:19:32'),
(27, 5, NULL, '2026-04-28', '2026-04-28 09:30:00', '2026-04-28 17:30:00', '2026-04-28 09:21:07', '2026-04-28 18:30:00', 548, 0,   0,   60, 0,  'present',    'approved', 0, '2026-04-28 01:40:10', '2026-04-28 01:57:10'),
(33, 9, 77,   '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', NULL,                  NULL,                  0,   0,   0,   0,  0,  'incomplete', 'none',     0, '2026-04-29 06:08:15', '2026-04-29 06:08:15'),
(34, 8, 78,   '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', NULL,                  NULL,                  0,   0,   0,   0,  0,  'incomplete', 'none',     0, '2026-04-29 06:08:30', '2026-04-29 06:08:30'),
(38, 7, 81,   '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', NULL,                  NULL,                  0,   0,   0,   0,  0,  'incomplete', 'none',     0, '2026-04-28 07:35:08', '2026-04-28 07:35:08'),
-- Rows present only in doc1 (employee 6 / Edrian):
(39, 6, 83,   '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', '2026-04-30 10:00:58', '2026-04-30 17:30:23', 7,   90,  0,   0,  60, 'present',    'none',     0, '2026-04-29 05:31:24', '2026-04-30 09:30:23'),
(40, 6, 84,   '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', NULL,                  NULL,                  0,   0,   0,   0,  0,  'incomplete', 'none',     0, '2026-04-29 05:31:24', '2026-04-29 05:31:24'),
(41, 6, 85,   '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', '2026-04-29 13:18:59', '2026-04-29 13:19:07', 0,   288, 251, 0,  0,  'present',    'none',     0, '2026-04-29 05:32:14', '2026-04-29 05:33:02');

-- --------------------------------------------------------
-- Table structure for table `departments`
-- (doc2 schema used: includes `color` column; doc2 has department data)
-- --------------------------------------------------------

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `color` varchar(20) DEFAULT '#4e73df'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `parent_id`, `created_at`, `color`) VALUES
(1,  'FIN',  'Finance',             NULL, '2026-04-29 03:59:54', '#4e73df'),
(2,  'HR',   'Human Resources',     NULL, '2026-04-29 04:57:16', '#4e73df'),
(3,  'CC',   'Customer Care',       NULL, '2026-04-29 05:02:13', '#4e73df'),
(6,  'APHI', 'Acer Philippines',    3,    '2026-04-29 05:04:41', '#4e73df'),
(7,  'LOG',  'Logistics',           NULL, '2026-04-29 05:34:31', '#1dd353'),
(10, 'ITSM', 'IT Service Management', NULL, '2026-04-29 05:51:51', '#ffffff'),
(11, 'AUSA', 'Acer USA',            3,    '2026-04-29 05:59:44', '#ee1111'),
(12, 'AOCC', 'Acer Oceania',        3,    '2026-04-29 06:01:24', '#e0b310');

-- --------------------------------------------------------
-- Table structure for table `employees`
-- --------------------------------------------------------

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','employee','workforce') NOT NULL DEFAULT 'employee',
  `department_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `employees` (`id`, `name`, `email`, `password`, `role`, `department_id`) VALUES
(2, 'User',    'user@gmail.com',    'user123',  'employee',  NULL),
(3, 'Admin',   'admin@gmail.com',   'admin123', 'admin',     NULL),
(4, 'User1',   'user1@gmail.com',   'user123',  'employee',  NULL),
(5, 'Earl',    'earl@gmail.com',    '123',      'employee',  NULL),
(6, 'Edrian',  'edrian@gmail.com',  '123',      'workforce', NULL),
(7, 'Jignesh', 'jigs@gmail.com',    '123',      'employee',  NULL),
(8, 'Justine', 'justine@gmail.com', '123',      'employee',  NULL),
(9, 'Dirk',    'dirk@gmail.com',    '123',      'employee',  NULL);

-- --------------------------------------------------------
-- Table structure for table `leave_requests`
-- --------------------------------------------------------

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `leave_type` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `selected_dates` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- (No data in either dump)

-- --------------------------------------------------------
-- Table structure for table `logs`
-- (doc1 has log data; doc2 logs table is empty)
-- --------------------------------------------------------

CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `log_type` enum('IN','OUT','BREAK_IN','BREAK_OUT') NOT NULL,
  `log_time` datetime NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `accuracy` float DEFAULT NULL,
  `is_within_office` tinyint(1) NOT NULL DEFAULT 0,
  `distance_meters` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `logs` (`id`, `employee_id`, `log_type`, `log_time`, `longitude`, `latitude`, `accuracy`, `is_within_office`, `distance_meters`, `created_at`) VALUES
(513, 6, 'IN',        '2026-04-29 13:18:59', 120.9954744, 14.5842803, 55, 1, 22.2173, '2026-04-29 05:18:59'),
(514, 6, 'OUT',       '2026-04-29 13:19:07', 120.9954744, 14.5842803, 55, 1, 22.2173, '2026-04-29 05:19:07'),
(515, 6, 'IN',        '2026-04-29 13:33:02', 120.9954907, 14.5842691, 55, 1, 21.8787, '2026-04-29 05:33:02'),
(516, 6, 'BREAK_IN',  '2026-04-29 13:33:13', 120.9954907, 14.5842691, 55, 1, 21.88,   '2026-04-29 05:33:13'),
(517, 6, 'BREAK_OUT', '2026-04-29 13:33:19', 120.9954907, 14.5842691, 55, 1, 21.88,   '2026-04-29 05:33:19'),
(518, 6, 'OUT',       '2026-04-29 13:33:29', 120.9954907, 14.5842691, 55, 1, 21.8787, '2026-04-29 05:33:29'),
(519, 6, 'IN',        '2026-04-30 10:00:58', 120.9954947, 14.5842654, 55, 1, 21.9055, '2026-04-30 02:00:58'),
(520, 6, 'BREAK_IN',  '2026-04-30 12:00:05', 120.9954947, 14.5842654, 55, 1, 21.91,   '2026-04-30 04:00:05'),
(521, 6, 'BREAK_OUT', '2026-04-30 13:00:13', 120.9954589, 14.5842890, 55, 1, 22.87,   '2026-04-30 05:00:13'),
(522, 6, 'OUT',       '2026-04-30 17:30:23', 120.9954589, 14.5842890, 55, 1, 22.8724, '2026-04-30 09:30:23');

-- --------------------------------------------------------
-- Table structure for table `log_edit_requests`
-- (doc1 schema used: more evolved, with request_type, requested_time_in,
--  and updated requested_time_out as NULLable; doc1 also has row id=4)
-- --------------------------------------------------------

CREATE TABLE `log_edit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `actual_time_in` datetime NOT NULL,
  `request_type` enum('time_in','time_out','both') NOT NULL DEFAULT 'time_out',
  `requested_time_in` datetime DEFAULT NULL,
  `requested_time_out` datetime DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `log_edit_requests` (`id`, `employee_id`, `attendance_id`, `work_date`, `actual_time_in`, `request_type`, `requested_time_in`, `requested_time_out`, `reason`, `status`, `created_at`, `updated_at`) VALUES
(3, 5, 27, '2026-04-28', '2026-04-28 09:21:07', 'time_out', NULL,                  '2026-04-28 18:30:00', 'accidentally clicked time out',                        'approved', '2026-04-28 01:43:57', '2026-04-28 01:44:05'),
(4, 6, 41, '2026-04-29', '2026-04-29 13:18:59', 'both',     '2026-04-29 08:30:00', '2026-04-29 18:00:00', 'Forgot to time in early and accidentally time out', 'pending',  '2026-04-29 06:23:49', '2026-04-29 06:23:49');

-- --------------------------------------------------------
-- Table structure for table `ob_requests`
-- --------------------------------------------------------

CREATE TABLE `ob_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `ob_date` date NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- (No data in either dump)

-- --------------------------------------------------------
-- Table structure for table `overtime_requests`
-- --------------------------------------------------------

CREATE TABLE `overtime_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `overtime_requests` (`id`, `employee_id`, `date`, `time_in`, `time_out`, `reason`, `status`, `created_at`) VALUES
(15, 5, '2026-04-28', '17:30:00', '18:30:00', 'Client needed more understanding', 'approved', '2026-04-28 01:53:47');

-- --------------------------------------------------------
-- Table structure for table `schedules`
-- (doc1 has schedules 83, 84, 85 not in doc2)
-- --------------------------------------------------------

CREATE TABLE `schedules` (
  `id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `schedule_date` date NOT NULL,
  `scheduled_start` datetime DEFAULT NULL,
  `scheduled_end` datetime DEFAULT NULL,
  `is_rest_day` tinyint(1) DEFAULT 0,
  `status` enum('approved','pending') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `schedules` (`id`, `employee_id`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `status`) VALUES
(73, 5, '2026-04-28', '2026-04-28 09:30:00', '2026-04-28 17:30:00', 0, 'approved'),
(74, 5, '2026-04-29', '2026-04-29 09:30:00', '2026-04-29 17:30:00', 0, 'approved'),
(75, 5, '2026-04-30', '2026-04-30 09:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(76, 5, '2026-05-01', '2026-05-01 09:30:00', '2026-05-01 17:30:00', 0, 'approved'),
(77, 9, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(78, 8, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(81, 7, '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', 0, 'approved'),
-- Schedules present only in doc1 (employee 6 / Edrian):
(83, 6, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(84, 6, '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', 0, 'approved'),
(85, 6, '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', 0, 'approved');

-- --------------------------------------------------------
-- Table structure for table `system_state`
-- --------------------------------------------------------

CREATE TABLE `system_state` (
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_state` (`key_name`, `value`) VALUES
('attendance_last_finalize', '2026-04-21');

-- --------------------------------------------------------
-- Indexes
-- --------------------------------------------------------

ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_employee_date` (`employee_id`,`work_date`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_date` (`work_date`),
  ADD KEY `idx_schedule_time` (`scheduled_start`,`scheduled_end`);

ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `parent_id` (`parent_id`);

ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_time` (`employee_id`,`log_time`),
  ADD KEY `idx_log_type` (`log_type`);

ALTER TABLE `log_edit_requests`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ob_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ob_employee_date` (`employee_id`,`ob_date`);

ALTER TABLE `overtime_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_date` (`employee_id`,`schedule_date`);

ALTER TABLE `system_state`
  ADD PRIMARY KEY (`key_name`);

-- --------------------------------------------------------
-- AUTO_INCREMENT values (using the highest from either dump)
-- --------------------------------------------------------

ALTER TABLE `attendances`    MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;
ALTER TABLE `departments`    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,    AUTO_INCREMENT=13;
ALTER TABLE `employees`      MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,             AUTO_INCREMENT=10;
ALTER TABLE `leave_requests` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,             AUTO_INCREMENT=16;
ALTER TABLE `logs`           MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT,          AUTO_INCREMENT=523;
ALTER TABLE `log_edit_requests` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `ob_requests`    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,             AUTO_INCREMENT=5;
ALTER TABLE `overtime_requests` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,         AUTO_INCREMENT=16;
ALTER TABLE `schedules`      MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT,          AUTO_INCREMENT=86;

-- --------------------------------------------------------
-- Foreign key constraints
-- --------------------------------------------------------

ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

ALTER TABLE `overtime_requests`
  ADD CONSTRAINT `overtime_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;