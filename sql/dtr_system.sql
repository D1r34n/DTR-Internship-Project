-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2026 at 08:12 AM
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
(69, 2, '2026-04-17', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(71, 2, '2026-04-16', '08:30:00', '17:30:00', NULL, NULL, 0.00, 0, 0, 0, 'absent', 'none'),
(126, 2, '2026-04-20', '09:00:00', '17:00:00', '2026-04-20 11:30:58', '2026-04-20 14:07:28', 1.61, 150, 172, 0, 'late', 'none');

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
(496, 2, 'login', '2026-04-20 11:41:18'),
(497, 2, 'logout', '2026-04-20 11:41:19'),
(498, 2, 'login', '2026-04-20 11:41:20'),
(499, 2, 'logout', '2026-04-20 11:41:20'),
(500, 2, 'logout', '2026-04-20 11:41:21'),
(501, 2, 'login', '2026-04-20 11:41:22'),
(502, 2, 'logout', '2026-04-20 11:41:26'),
(503, 2, 'login', '2026-04-20 11:43:50'),
(504, 2, 'logout', '2026-04-20 11:43:58'),
(505, 2, 'login', '2026-04-20 11:44:06'),
(506, 2, 'logout', '2026-04-20 11:44:06'),
(507, 2, 'logout', '2026-04-20 11:55:13'),
(508, 2, 'login', '2026-04-20 11:55:22'),
(509, 2, 'logout', '2026-04-20 13:09:12'),
(510, 2, 'login', '2026-04-20 13:09:17'),
(511, 2, 'logout', '2026-04-20 13:12:32'),
(512, 2, 'login', '2026-04-20 13:13:14'),
(513, 2, 'logout', '2026-04-20 13:13:16'),
(514, 2, 'login', '2026-04-20 13:24:46'),
(515, 2, 'logout', '2026-04-20 13:24:47'),
(516, 2, 'login', '2026-04-20 13:25:30'),
(517, 2, 'logout', '2026-04-20 13:26:24'),
(518, 2, 'login', '2026-04-20 13:29:53'),
(519, 2, 'logout', '2026-04-20 13:41:42'),
(520, 2, 'login', '2026-04-20 13:53:07'),
(521, 2, 'logout', '2026-04-20 13:53:07'),
(522, 2, 'logout', '2026-04-20 13:53:09'),
(523, 2, 'login', '2026-04-20 13:53:09'),
(524, 2, 'logout', '2026-04-20 13:54:41'),
(525, 2, 'login', '2026-04-20 13:54:43'),
(526, 2, 'logout', '2026-04-20 13:54:44'),
(527, 2, 'login', '2026-04-20 13:54:46'),
(528, 2, 'logout', '2026-04-20 13:55:26'),
(529, 2, 'login', '2026-04-20 13:55:28'),
(530, 2, 'logout', '2026-04-20 13:58:34'),
(531, 2, 'login', '2026-04-20 13:59:49'),
(532, 2, 'logout', '2026-04-20 13:59:50'),
(533, 2, 'login', '2026-04-20 14:00:20'),
(534, 2, 'logout', '2026-04-20 14:00:21'),
(535, 2, 'login', '2026-04-20 14:00:23'),
(536, 2, 'logout', '2026-04-20 14:01:06'),
(537, 2, 'login', '2026-04-20 14:01:15'),
(538, 2, 'logout', '2026-04-20 14:01:16'),
(539, 2, 'login', '2026-04-20 14:03:51'),
(540, 2, 'logout', '2026-04-20 14:03:52'),
(541, 2, 'login', '2026-04-20 14:07:22'),
(542, 2, 'logout', '2026-04-20 14:07:28');

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
(118, 2, '2026-04-27', '09:00:00', '17:00:00', 0),
(119, 2, '2026-04-28', '09:00:00', '17:00:00', 0),
(127, 2, '2026-04-12', NULL, NULL, 1),
(128, 2, '2026-04-13', '08:30:00', '17:30:00', 0),
(129, 2, '2026-04-14', '08:30:00', '17:30:00', 0),
(130, 2, '2026-04-15', '08:30:00', '17:30:00', 0),
(131, 2, '2026-04-16', '08:30:00', '17:30:00', 0),
(132, 2, '2026-04-17', '08:30:00', '17:30:00', 0),
(133, 2, '2026-04-18', NULL, NULL, 1),
(134, 2, '2026-04-20', '09:00:00', '17:00:00', 0),
(135, 2, '2026-04-21', '09:00:00', '17:00:00', 0),
(136, 2, '2026-04-22', '09:00:00', '17:00:00', 0),
(137, 2, '2026-04-23', '09:00:00', '17:00:00', 0),
(138, 2, '2026-04-24', '09:00:00', '17:00:00', 0),
(139, 2, '2026-04-25', NULL, NULL, 1),
(140, 2, '2026-04-26', NULL, NULL, 1);

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
('attendance_last_finalize', '2026-04-20');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=543;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

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
