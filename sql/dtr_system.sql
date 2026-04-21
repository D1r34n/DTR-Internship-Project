-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 11:34 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `scheduled_time_in` time NOT NULL,
  `scheduled_time_out` time NOT NULL,
  `actual_time_in` datetime DEFAULT NULL,
  `actual_time_out` datetime DEFAULT NULL,
  `total_work_hours` decimal(5,2) DEFAULT NULL,
  `late_minutes` int(11) DEFAULT 0,
  `undertime_minutes` int(11) DEFAULT 0,
  `overtime_minutes` int(11) DEFAULT 0,
  `status` enum('present','late','absent','incomplete') NOT NULL DEFAULT 'absent',
  `overtime_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `date`, `scheduled_time_in`, `scheduled_time_out`, `actual_time_in`, `actual_time_out`, `total_work_hours`, `late_minutes`, `undertime_minutes`, `overtime_minutes`, `status`, `overtime_status`) VALUES
(310, 2, '2026-04-13', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(311, 2, '2026-04-14', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(312, 2, '2026-04-15', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(313, 2, '2026-04-16', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(314, 2, '2026-04-17', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(315, 2, '2026-04-20', '09:00:00', '14:00:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(407, 2, '2026-04-21', '09:00:00', '14:00:00', '2026-04-21 08:04:08', '2026-04-21 13:05:16', 0.00, 0, 54, 0, 'incomplete', 'none'),
(421, 2, '2026-04-22', '09:00:00', '14:00:00', '2026-04-22 08:05:27', '2026-04-22 08:15:37', 0.17, 0, 344, 0, 'incomplete', 'none');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','employee','','') NOT NULL DEFAULT 'employee'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `email`, `password`, `role`) VALUES
(2, 'User', 'user@gmail.com', 'user123', 'employee'),
(3, 'Admin', 'admin@gmail.com', 'admin123', 'admin'),
(4, 'User1', 'user1@gmail.com', 'user123', 'employee');

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
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `log_type` enum('login','logout') DEFAULT NULL,
  `log_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `employee_id`, `log_type`, `log_time`) VALUES
(972, 2, 'login', '2026-04-21 13:05:14'),
(973, 2, 'logout', '2026-04-21 13:05:16'),
(974, 2, 'login', '2026-04-22 08:05:27'),
(975, 2, 'logout', '2026-04-22 08:15:23'),
(976, 2, 'login', '2026-04-22 08:15:30'),
(977, 2, 'logout', '2026-04-22 08:15:37');

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
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `work_date` date DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `is_rest_day` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `work_date`, `time_in`, `time_out`, `is_rest_day`) VALUES
(110, 2, '2026-06-19', '08:00:00', '16:00:00', 0),
(111, 2, '2026-06-20', NULL, NULL, 1),
(112, 2, '2026-06-21', NULL, NULL, 1),
(127, 2, '2026-04-12', NULL, NULL, 1),
(128, 2, '2026-04-13', '08:30:00', '17:30:00', 0),
(129, 2, '2026-04-14', '08:30:00', '17:30:00', 0),
(130, 2, '2026-04-15', '08:30:00', '17:30:00', 0),
(131, 2, '2026-04-16', '08:30:00', '17:30:00', 0),
(132, 2, '2026-04-17', '08:30:00', '17:30:00', 0),
(133, 2, '2026-04-18', NULL, NULL, 1),
(162, 2, '2026-04-20', '09:00:00', '14:00:00', 0),
(163, 2, '2026-04-21', '09:00:00', '14:00:00', 0),
(164, 2, '2026-04-22', '09:00:00', '14:00:00', 0),
(165, 2, '2026-04-23', '09:00:00', '14:00:00', 0),
(166, 2, '2026-04-24', '09:00:00', '14:00:00', 0),
(167, 2, '2026-04-25', NULL, NULL, 1),
(168, 2, '2026-04-26', NULL, NULL, 1),
(169, 2, '2026-04-27', '18:00:00', '01:00:00', 0),
(170, 2, '2026-04-28', '18:00:00', '01:00:00', 0),
(171, 2, '2026-04-29', '18:00:00', '01:00:00', 0),
(172, 2, '2026-04-30', '18:00:00', '01:00:00', 0),
(173, 2, '2026-05-01', '18:00:00', '01:00:00', 0),
(174, 2, '2026-05-02', NULL, NULL, 1),
(175, 2, '2026-05-03', NULL, NULL, 1);

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
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_date` (`employee_id`,`date`);

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
  ADD KEY `employee_id` (`employee_id`);

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
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `system_state`
--
ALTER TABLE `system_state`
  ADD PRIMARY KEY (`key_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=423;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=978;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  ADD CONSTRAINT `overtime_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
