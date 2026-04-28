-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2026 at 09:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dtr_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendances`
--

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

--
-- Dumping data for table `attendances`
--

INSERT INTO `attendances` (`id`, `employee_id`, `schedule_id`, `work_date`, `scheduled_start`, `scheduled_end`, `actual_time_in`, `actual_time_out`, `total_work_minutes`, `late_minutes`, `undertime_minutes`, `overtime_minutes`, `break_minutes`, `status`, `overtime_status`, `missed_time_out`, `created_at`, `updated_at`) VALUES
(20, 5, 74, '2026-04-29', '2026-04-29 09:30:00', '2026-04-29 17:30:00', '2026-04-29 10:18:22', '2026-04-29 10:18:27', 0, 48, 432, 0, 0, 'present', 'none', 0, '2026-04-28 01:19:32', '2026-04-29 02:18:27'),
(21, 5, 75, '2026-04-30', '2026-04-30 09:30:00', '2026-04-30 17:30:00', '2026-04-30 09:30:13', '2026-04-30 18:00:51', 9, 0, 0, 31, 60, 'present', 'none', 0, '2026-04-28 01:19:32', '2026-04-30 10:00:51'),
(22, 5, 76, '2026-05-01', '2026-05-01 09:30:00', '2026-05-01 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-28 01:19:32', '2026-04-28 01:19:32'),
(27, 5, NULL, '2026-04-28', '2026-04-28 09:30:00', '2026-04-28 17:30:00', '2026-04-28 09:21:07', '2026-04-28 18:30:00', 548, 0, 0, 60, 0, 'present', 'approved', 0, '2026-04-28 01:40:10', '2026-04-28 01:57:10'),
(33, 9, 77, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-29 06:08:15', '2026-04-29 06:08:15'),
(34, 8, 78, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-29 06:08:30', '2026-04-29 06:08:30'),
(38, 7, 81, '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-28 07:35:08', '2026-04-28 07:35:08');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','employee','workforce') NOT NULL DEFAULT 'employee',
  `department` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `email`, `password`, `role`, `department`) VALUES
(2, 'User', 'user@gmail.com', 'user123', 'employee', 'CSS'),
(3, 'Admin', 'admin@gmail.com', 'admin123', 'admin', 'HR'),
(4, 'User1', 'user1@gmail.com', 'user123', 'employee', 'CSS'),
(5, 'Earl', 'earl@gmail.com', '123', 'employee', 'CSS'),
(6, 'Edrian', 'edrian@gmail.com', '123', 'workforce', 'CSS'),
(7, 'Jignesh', 'jigs@gmail.com', '123', 'employee', 'CSS'),
(8, 'Justine', 'justine@gmail.com', '123', 'employee', 'HR'),
(9, 'Dirk', 'dirk@gmail.com', '123', 'employee', 'HR');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

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

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `employee_id`, `log_type`, `log_time`, `longitude`, `latitude`, `accuracy`, `is_within_office`, `distance_meters`, `created_at`) VALUES
(496, 5, 'IN', '2026-04-28 09:21:07', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:21:07'),
(497, 5, 'OUT', '2026-04-28 09:22:58', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:22:58'),
(498, 5, 'IN', '2026-04-28 09:27:27', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:27:27'),
(499, 5, 'OUT', '2026-04-28 09:27:34', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:27:34'),
(500, 5, 'IN', '2026-04-28 09:40:09', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:40:09'),
(501, 5, 'OUT', '2026-04-28 09:40:22', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:40:22'),
(502, 5, 'IN', '2026-04-28 09:57:53', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:57:53'),
(503, 5, 'OUT', '2026-04-28 09:58:00', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-28 01:58:00'),
(504, 5, 'IN', '2026-04-29 10:18:22', 120.9954844, 14.5842740, 55, 1, 21.9409, '2026-04-29 02:18:22'),
(505, 5, 'OUT', '2026-04-29 10:18:27', 120.9954517, 14.5842952, 55, 1, 23.0766, '2026-04-29 02:18:27'),
(506, 5, 'IN', '2026-04-30 09:30:13', 120.9954896, 14.5842688, 55, 1, 21.9806, '2026-04-30 01:30:13'),
(507, 5, 'BREAK_IN', '2026-04-30 12:00:22', 120.9954571, 14.5842904, 55, 1, 22.93, '2026-04-30 04:00:22'),
(508, 5, 'BREAK_OUT', '2026-04-30 13:00:30', 120.9954571, 14.5842904, 55, 1, 22.93, '2026-04-30 05:00:30'),
(509, 5, 'OUT', '2026-04-30 18:00:51', 120.9954819, 14.5842749, 55, 1, 22.06, '2026-04-30 10:00:51');

-- --------------------------------------------------------

--
-- Table structure for table `log_edit_requests`
--

CREATE TABLE `log_edit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `actual_time_in` datetime NOT NULL,
  `requested_time_out` datetime NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_edit_requests`
--

INSERT INTO `log_edit_requests` (`id`, `employee_id`, `attendance_id`, `work_date`, `actual_time_in`, `requested_time_out`, `reason`, `status`, `created_at`, `updated_at`) VALUES
(3, 5, 27, '2026-04-28', '2026-04-28 09:21:07', '2026-04-28 18:30:00', 'accidentally clicked time out', 'approved', '2026-04-28 01:43:57', '2026-04-28 01:44:05');

-- --------------------------------------------------------

--
-- Table structure for table `ob_requests`
--

CREATE TABLE `ob_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `ob_date` date NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `overtime_requests`
--

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

--
-- Dumping data for table `overtime_requests`
--

INSERT INTO `overtime_requests` (`id`, `employee_id`, `date`, `time_in`, `time_out`, `reason`, `status`, `created_at`) VALUES
(15, 5, '2026-04-28', '17:30:00', '18:30:00', 'Client needed more understanding', 'approved', '2026-04-28 01:53:47');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `schedule_date` date NOT NULL,
  `scheduled_start` datetime DEFAULT NULL,
  `scheduled_end` datetime DEFAULT NULL,
  `is_rest_day` tinyint(1) DEFAULT 0,
  `status` enum('approved','pending') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `status`) VALUES
(73, 5, '2026-04-28', '2026-04-28 09:30:00', '2026-04-28 17:30:00', 0, 'approved'),
(74, 5, '2026-04-29', '2026-04-29 09:30:00', '2026-04-29 17:30:00', 0, 'approved'),
(75, 5, '2026-04-30', '2026-04-30 09:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(76, 5, '2026-05-01', '2026-05-01 09:30:00', '2026-05-01 17:30:00', 0, 'approved'),
(77, 9, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(78, 8, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', 0, 'approved'),
(81, 7, '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', 0, 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `system_state`
--

CREATE TABLE `system_state` (
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_state`
--

INSERT INTO `system_state` (`key_name`, `value`) VALUES
('attendance_last_finalize', '2026-04-21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_employee_date` (`employee_id`,`work_date`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_date` (`work_date`),
  ADD KEY `idx_schedule_time` (`scheduled_start`,`scheduled_end`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_time` (`employee_id`,`log_time`),
  ADD KEY `idx_log_type` (`log_type`);

--
-- Indexes for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ob_requests`
--
ALTER TABLE `ob_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ob_employee_date` (`employee_id`,`ob_date`);

--
-- Indexes for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_date` (`employee_id`,`schedule_date`);

--
-- Indexes for table `system_state`
--
ALTER TABLE `system_state`
  ADD PRIMARY KEY (`key_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=510;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ob_requests`
--
ALTER TABLE `ob_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  ADD CONSTRAINT `overtime_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
