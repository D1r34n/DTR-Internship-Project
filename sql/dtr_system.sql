-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 11:28 PM
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
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `total_work_hours` decimal(4,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `employee_id`, `date`, `time_in`, `time_out`, `total_work_hours`, `status`) VALUES
(6, 2, '2026-04-17', '09:28:23', '15:32:53', 6.08, 'completed'),
(7, 3, '2026-04-17', '16:05:37', '16:05:48', 0.00, 'completed'),
(8, 4, '2026-04-17', '16:06:11', '16:06:12', 0.00, 'completed'),
(9, 2, '2026-04-18', '21:04:57', '21:04:58', 0.00, 'completed'),
(10, 2, '2026-04-19', '19:20:17', '20:41:38', 1.36, 'completed');

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
(305, 2, 'login', '2026-04-17 09:28:23'),
(306, 2, 'logout', '2026-04-17 09:28:27'),
(307, 2, 'login', '2026-04-17 09:28:34'),
(308, 2, 'logout', '2026-04-17 09:28:37'),
(309, 2, 'login', '2026-04-17 09:30:04'),
(310, 2, 'logout', '2026-04-17 09:31:09'),
(311, 2, 'login', '2026-04-17 09:32:31'),
(312, 2, 'logout', '2026-04-17 09:32:36'),
(313, 2, 'login', '2026-04-17 09:33:17'),
(314, 2, 'logout', '2026-04-17 09:33:22'),
(315, 2, 'login', '2026-04-17 09:33:25'),
(316, 2, 'logout', '2026-04-17 09:34:04'),
(317, 2, 'login', '2026-04-17 09:35:56'),
(318, 2, 'logout', '2026-04-17 09:35:58'),
(319, 2, 'login', '2026-04-17 09:35:59'),
(320, 2, 'logout', '2026-04-17 09:36:02'),
(321, 2, 'login', '2026-04-17 09:36:04'),
(322, 2, 'logout', '2026-04-17 09:36:05'),
(323, 2, 'login', '2026-04-17 09:46:20'),
(324, 2, 'logout', '2026-04-17 09:46:23'),
(325, 2, 'login', '2026-04-17 14:03:05'),
(326, 2, 'logout', '2026-04-17 14:03:05'),
(327, 2, 'logout', '2026-04-17 14:07:27'),
(328, 2, 'login', '2026-04-17 14:07:32'),
(329, 2, 'logout', '2026-04-17 14:10:24'),
(330, 2, 'login', '2026-04-17 15:23:20'),
(331, 2, 'logout', '2026-04-17 15:23:22'),
(332, 2, 'login', '2026-04-17 15:29:10'),
(333, 2, 'logout', '2026-04-17 15:29:11'),
(334, 2, 'login', '2026-04-17 15:32:52'),
(335, 2, 'logout', '2026-04-17 15:32:53'),
(336, 3, 'login', '2026-04-17 16:05:37'),
(337, 3, 'logout', '2026-04-17 16:05:48'),
(338, 4, 'login', '2026-04-17 16:06:11'),
(339, 4, 'logout', '2026-04-17 16:06:12'),
(340, 2, 'login', '2026-04-18 21:04:57'),
(341, 2, 'logout', '2026-04-18 21:04:58'),
(342, 2, 'login', '2026-04-19 19:20:17'),
(343, 2, 'logout', '2026-04-19 19:20:19'),
(344, 2, 'login', '2026-04-19 19:20:38'),
(345, 2, 'logout', '2026-04-19 19:21:01'),
(346, 2, 'login', '2026-04-19 19:21:27'),
(347, 2, 'logout', '2026-04-19 19:21:44'),
(348, 2, 'login', '2026-04-19 19:38:17'),
(349, 2, 'logout', '2026-04-19 19:38:18'),
(350, 2, 'login', '2026-04-19 20:41:35'),
(351, 2, 'logout', '2026-04-19 20:41:38');

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
(50, 2, '2026-04-20', '08:30:00', '17:30:00', 0),
(51, 2, '2026-04-21', '08:30:00', '17:30:00', 0),
(52, 2, '2026-04-22', '08:30:00', '17:30:00', 0),
(53, 2, '2026-04-23', '08:30:00', '17:30:00', 0),
(54, 2, '2026-04-24', '08:30:00', '17:30:00', 0),
(55, 2, '2026-04-25', NULL, NULL, 1),
(56, 2, '2026-04-26', NULL, NULL, 1),
(57, 2, '2026-05-01', '08:30:00', '17:30:00', 0),
(58, 2, '2026-05-02', NULL, NULL, 1),
(59, 2, '2026-05-03', NULL, NULL, 1),
(60, 2, '2026-05-04', '08:30:00', '17:30:00', 0),
(61, 2, '2026-05-05', '08:30:00', '17:30:00', 0),
(62, 2, '2026-05-06', '08:30:00', '17:30:00', 0),
(63, 2, '2026-05-07', '08:30:00', '17:30:00', 0),
(85, 2, '2026-02-08', NULL, NULL, 1),
(86, 2, '2026-02-09', '08:00:00', '17:00:00', 0),
(87, 2, '2026-02-10', '08:00:00', '17:00:00', 0),
(88, 2, '2026-02-11', '08:00:00', '17:00:00', 0),
(89, 2, '2026-02-12', '08:00:00', '17:00:00', 0),
(90, 2, '2026-02-13', '08:00:00', '17:00:00', 0),
(91, 2, '2026-02-14', NULL, NULL, 1),
(106, 2, '2026-06-15', '08:00:00', '16:00:00', 0),
(107, 2, '2026-06-16', '08:00:00', '16:00:00', 0),
(108, 2, '2026-06-17', '08:00:00', '16:00:00', 0),
(109, 2, '2026-06-18', '08:00:00', '16:00:00', 0),
(110, 2, '2026-06-19', '08:00:00', '16:00:00', 0),
(111, 2, '2026-06-20', NULL, NULL, 1),
(112, 2, '2026-06-21', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tardiness`
--

CREATE TABLE `tardiness` (
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `scheduled_time_in` time NOT NULL,
  `actual_time_in` time NOT NULL,
  `minutes_late` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `tardiness`
--
ALTER TABLE `tardiness`
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=352;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

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

--
-- Constraints for table `tardiness`
--
ALTER TABLE `tardiness`
  ADD CONSTRAINT `employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
