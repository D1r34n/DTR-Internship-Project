-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 10:15 AM
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
(3, 2, 62, '2026-04-27', '2026-04-27 00:00:00', '2026-04-27 09:00:00', NULL, NULL, 0, 0, 0, 0, NULL, 'incomplete', 'none', 0, '2026-04-27 00:42:36', '2026-04-27 00:42:36'),
(4, 5, 63, '2026-04-28', '2026-04-28 00:00:00', '2026-04-28 09:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:28', '2026-04-27 05:37:32'),
(5, 5, 64, '2026-04-29', '2026-04-29 00:00:00', '2026-04-29 09:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:28', '2026-04-27 05:37:32'),
(6, 5, 65, '2026-04-30', '2026-04-30 00:00:00', '2026-04-30 09:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:28', '2026-04-27 05:37:32'),
(7, 5, 66, '2026-05-01', '2026-05-01 00:00:00', '2026-05-01 09:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:28', '2026-04-27 05:37:32'),
(8, 5, 67, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:28', '2026-04-27 01:42:28'),
(9, 5, 68, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:29', '2026-04-27 01:42:29'),
(10, 5, 69, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:29', '2026-04-27 01:42:29'),
(11, 5, 70, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:29', '2026-04-27 01:42:29'),
(12, 5, 71, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-27 01:42:29', '2026-04-27 01:42:29');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `email`, `password`, `role`) VALUES
(2, 'User', 'user@gmail.com', 'user123', 'employee'),
(3, 'Admin', 'admin@gmail.com', 'admin123', 'admin'),
(4, 'User1', 'user1@gmail.com', 'user123', 'employee'),
(5, 'Earl', 'earl@gmail.com', '123', 'employee');

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

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `selected_dates`, `reason`, `status`, `created_at`) VALUES
(13, 5, 'vacation leave', '2026-04-30', '2026-04-30', '[\"2026-04-30\"]', 'Baguio', 'approved', '2026-04-27 03:04:09'),
(14, 5, 'vacation leave', '2026-05-06', '2026-05-06', '[\"2026-05-06\"]', 'Boracay', 'pending', '2026-04-27 05:57:49'),
(15, 5, 'birthday leave', '2026-05-07', '2026-05-07', '[\"2026-05-07\"]', 'Bday ko', 'rejected', '2026-04-27 05:58:20');

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
(485, 5, 'IN', '2026-04-27 09:42:55', 120.9954959, 14.5842646, 55, 1, 21.8967, '2026-04-27 01:42:55'),
(486, 5, 'OUT', '2026-04-27 09:46:32', 120.9954940, 14.5842660, 55, 1, 21.9074, '2026-04-27 01:46:32'),
(487, 5, 'IN', '2026-04-27 09:46:42', 120.9954940, 14.5842660, 55, 1, 21.9074, '2026-04-27 01:46:42'),
(488, 5, 'OUT', '2026-04-27 09:46:49', 120.9954940, 14.5842660, 55, 1, 21.9074, '2026-04-27 01:46:49'),
(489, 5, 'IN', '2026-04-27 13:25:39', 120.9954980, 14.5842638, 55, 1, 21.8113, '2026-04-27 05:25:39'),
(490, 5, 'OUT', '2026-04-27 13:28:04', 120.9955144, 14.5842516, 55, 1, 21.8638, '2026-04-27 05:28:04');

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

--
-- Dumping data for table `ob_requests`
--

INSERT INTO `ob_requests` (`id`, `employee_id`, `ob_date`, `client_name`, `reason`, `status`, `created_at`) VALUES
(2, 5, '2026-05-01', 'Edrian Evangelista', 'Meeting', 'approved', '2026-04-27 03:15:23'),
(3, 5, '2026-05-04', 'Dirk Del Mundo', 'Meeting', 'rejected', '2026-04-27 05:38:18'),
(4, 5, '2026-05-05', 'Jignesh Nate', 'Meeting', 'pending', '2026-04-27 05:55:36');

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
  `is_rest_day` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`) VALUES
(62, 2, '2026-04-27', '2026-04-27 00:00:00', '2026-04-27 09:00:00', 0),
(63, 5, '2026-04-28', '2026-04-28 00:00:00', '2026-04-28 09:00:00', 0),
(64, 5, '2026-04-29', '2026-04-29 00:00:00', '2026-04-29 09:00:00', 0),
(65, 5, '2026-04-30', '2026-04-30 00:00:00', '2026-04-30 09:00:00', 0),
(66, 5, '2026-05-01', '2026-05-01 00:00:00', '2026-05-01 09:00:00', 0),
(67, 5, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', 0),
(68, 5, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', 0),
(69, 5, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', 0),
(70, 5, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', 0),
(71, 5, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', 0);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=491;

--
-- AUTO_INCREMENT for table `ob_requests`
--
ALTER TABLE `ob_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

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
