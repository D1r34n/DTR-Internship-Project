-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2026 at 03:09 AM
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
(38, 7, 81, '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-28 07:35:08', '2026-04-28 07:35:08'),
(39, 6, 83, '2026-04-30', '2026-04-30 08:30:00', '2026-04-30 17:30:00', '2026-04-30 10:00:58', '2026-04-30 17:30:23', 7, 90, 0, 0, 60, 'present', 'none', 0, '2026-04-29 05:31:24', '2026-04-30 09:30:23'),
(40, 6, 84, '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-04-29 05:31:24', '2026-05-03 23:30:43'),
(41, 6, 85, '2026-04-29', '2026-04-29 08:30:00', '2026-04-29 17:30:00', '2026-04-29 13:18:59', '2026-04-29 13:19:07', 0, 288, 251, 0, 0, 'present', 'none', 0, '2026-04-29 05:32:14', '2026-04-29 05:33:02'),
(48, 6, 87, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', '2026-05-04 07:30:43', '2026-05-04 18:00:11', 10, 0, 0, 30, 60, 'present', 'none', 0, '2026-04-29 08:48:28', '2026-05-04 10:00:11'),
(49, 6, 88, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', '2026-05-05 08:30:00', '2026-05-05 18:00:00', 570, 0, 0, 30, 0, 'present', 'approved', 0, '2026-04-29 08:48:28', '2026-05-04 09:22:39'),
(50, 6, 89, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-29 08:48:28', '2026-04-29 08:48:28'),
(51, 6, 90, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-29 08:48:28', '2026-04-29 08:48:28'),
(52, 6, 91, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-04-29 08:48:28', '2026-04-29 08:48:28'),
(59, 2, NULL, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-05 08:39:47', '2026-05-05 08:39:47'),
(60, 2, NULL, '2026-05-02', '2026-05-02 09:00:00', '2026-05-02 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-05 08:39:47', '2026-05-05 08:39:47'),
(61, 2, NULL, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-05 08:39:47', '2026-05-05 08:39:47'),
(62, 2, NULL, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', '2026-05-04 11:23:52', '2026-05-04 13:24:55', 2, 143, 275, 0, 0, 'present', 'none', 0, '2026-05-05 08:39:47', '2026-05-05 08:39:47'),
(63, 2, NULL, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', '2026-05-05 16:39:47', '2026-05-05 16:40:02', 0, 459, 80, 0, 0, 'present', 'none', 0, '2026-05-05 08:39:47', '2026-05-05 08:40:02'),
(65, 2, NULL, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', '2026-05-06 14:14:18', '2026-05-06 14:14:23', 0, 314, 226, 0, 0, 'present', 'none', 0, '2026-05-06 06:14:18', '2026-05-06 06:14:23'),
(67, 5, NULL, '2026-05-02', '2026-05-02 07:00:00', '2026-05-02 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-06 06:16:03', '2026-05-06 06:16:03'),
(70, 5, NULL, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-06 06:16:03', '2026-05-06 06:16:03'),
(73, 5, NULL, '2026-05-04', '2026-05-04 07:00:00', '2026-05-04 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-06 06:16:03', '2026-05-06 06:16:03'),
(75, 5, NULL, '2026-05-05', '2026-05-05 07:00:00', '2026-05-05 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-06 06:16:03', '2026-05-06 06:16:03'),
(77, 5, NULL, '2026-05-06', '2026-05-06 07:00:00', '2026-05-06 16:00:00', '2026-05-06 14:16:03', '2026-05-06 14:16:09', 0, 436, 104, 0, 0, 'present', 'none', 0, '2026-05-06 06:16:03', '2026-05-06 06:16:09');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `color` varchar(20) DEFAULT '#4e73df'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `parent_id`, `created_at`, `color`) VALUES
(1, 'FIN', 'Finance', NULL, '2026-04-29 03:59:54', '#4e73df'),
(2, 'HR', 'Human Resources', NULL, '2026-04-29 04:57:16', '#4e73df'),
(3, 'CC', 'Call Center', NULL, '2026-04-29 05:02:13', '#1118d4'),
(6, 'APHI', 'Acer Philippines', 3, '2026-04-29 05:04:41', '#4e73df'),
(7, 'LOG', 'Logistics', NULL, '2026-04-29 05:34:31', '#1dd353'),
(10, 'ITSM', 'IT Service Management', NULL, '2026-04-29 05:51:51', '#ffffff'),
(11, 'AUSA', 'Acer USA', 3, '2026-04-29 05:59:44', '#ee1111'),
(12, 'AOCC', 'Acer Oceania', 3, '2026-04-29 06:01:24', '#c55c16'),
(13, 'KNDT', 'Kampon ni Dad Thonie', NULL, '2026-04-29 07:53:22', '#4e73df'),
(14, 'SOCMED', 'Social Media', 3, '2026-05-05 04:05:07', '#f529b4');

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
  `department_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `email`, `password`, `role`, `department_id`) VALUES
(2, 'User', 'user@gmail.com', 'user123', 'employee', 2),
(3, 'Admin', 'admin@gmail.com', 'admin123', 'admin', 7),
(4, 'User1', 'user1@gmail.com', 'user123', 'employee', 14),
(5, 'Earl', 'earl@gmail.com', '123', 'employee', 13),
(6, 'Edrian', 'edrian@gmail.com', '123', 'workforce', 13),
(7, 'Jignesh', 'jigs@gmail.com', '123', 'employee', 13),
(8, 'Justine', 'justine@gmail.com', '123', 'employee', 13),
(11, 'Dharmveer', 'dharm@gmail.com', '123', 'employee', NULL),
(12, 'Dirk', 'dirk@gmail.com', '123', 'workforce', 13);

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
(16, 5, 'vacation leave', '2026-05-06', '2026-05-06', '[\"2026-05-06\"]', 'beach vacation', 'rejected', '2026-05-05 09:51:25');

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
(585, 5, 'IN', '2026-05-06 15:14:07', 120.9954987, 14.5842598, 55, 1, 22.1215, '2026-05-06 07:14:07'),
(586, 5, 'OUT', '2026-05-06 15:14:15', 120.9954987, 14.5842598, 55, 1, 22.1215, '2026-05-06 07:14:15'),
(587, 5, 'IN', '2026-05-06 15:37:35', 120.9954942, 14.5842615, 55, 1, 22.2736, '2026-05-06 07:37:35'),
(588, 3, 'IN', '2026-05-06 17:15:01', 120.9954976, 14.5842605, 55, 1, 22.1243, '2026-05-06 09:15:01'),
(589, 3, 'OUT', '2026-05-06 17:15:07', 120.9954976, 14.5842605, 55, 1, 22.1243, '2026-05-06 09:15:07'),
(590, 5, 'OUT', '2026-05-06 17:24:33', 120.9955055, 14.5842558, 55, 1, 22.0234, '2026-05-06 09:24:33');

-- --------------------------------------------------------

--
-- Table structure for table `log_edit_requests`
--

CREATE TABLE `log_edit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED NOT NULL,
  `log_id` bigint(20) DEFAULT NULL,
  `work_date` date NOT NULL,
  `actual_time_in` datetime NOT NULL,
  `request_type` enum('time_in','time_out','both') NOT NULL DEFAULT 'time_out',
  `requested_time_in` datetime DEFAULT NULL,
  `requested_time_out` datetime DEFAULT NULL,
  `reason` text NOT NULL,
  `initiated_by_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_edit_requests`
--

INSERT INTO `log_edit_requests` (`id`, `employee_id`, `attendance_id`, `log_id`, `work_date`, `actual_time_in`, `request_type`, `requested_time_in`, `requested_time_out`, `reason`, `initiated_by_id`, `status`, `created_at`, `updated_at`) VALUES
(11, 5, 77, 585, '2026-05-06', '2026-05-06 14:16:03', 'time_in', '2026-05-06 15:14:00', NULL, 'incorrect time in', 5, 'pending', '2026-05-06 08:02:57', '2026-05-06 08:02:57'),
(12, 5, 77, 586, '2026-05-06', '2026-05-06 14:16:03', 'time_out', NULL, '2026-05-06 15:14:00', 'time out does not match actual time out', 12, 'pending', '2026-05-06 08:03:19', '2026-05-06 08:03:19');

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
(15, 5, '2026-04-28', '17:30:00', '18:30:00', 'Client needed more understanding', 'approved', '2026-04-28 01:53:47'),
(16, 6, '2026-05-05', '17:30:00', '18:00:00', 'reason', 'approved', '2026-05-04 09:22:10');

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
  `status` enum('approved','pending','rejected') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `status`) VALUES
(151, 2, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', 0, 'approved'),
(152, 2, '2026-05-02', '2026-05-02 09:00:00', '2026-05-02 18:00:00', 0, 'approved'),
(153, 2, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 18:00:00', 0, 'approved'),
(154, 2, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', 0, 'approved'),
(155, 2, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', 0, 'approved'),
(156, 2, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', 0, 'approved'),
(157, 2, '2026-05-07', '2026-05-07 09:00:00', '2026-05-07 18:00:00', 0, 'approved'),
(158, 2, '2026-05-08', '2026-05-08 09:00:00', '2026-05-08 18:00:00', 0, 'approved'),
(159, 2, '2026-05-09', '2026-05-09 09:00:00', '2026-05-09 18:00:00', 0, 'approved'),
(160, 2, '2026-05-10', '2026-05-10 09:00:00', '2026-05-10 18:00:00', 0, 'approved'),
(161, 2, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', 0, 'approved'),
(162, 2, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', 0, 'approved'),
(163, 2, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', 0, 'approved'),
(164, 2, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', 0, 'approved'),
(165, 2, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', 0, 'approved'),
(166, 2, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', 0, 'approved'),
(167, 2, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', 0, 'approved'),
(168, 2, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', 0, 'approved'),
(169, 2, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', 0, 'approved'),
(170, 2, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', 0, 'approved'),
(171, 5, '2026-05-02', '2026-05-02 07:00:00', '2026-05-02 16:00:00', 0, 'approved'),
(172, 5, '2026-05-03', '2026-05-03 07:00:00', '2026-05-03 16:00:00', 0, 'approved'),
(173, 5, '2026-05-04', '2026-05-04 07:00:00', '2026-05-04 16:00:00', 0, 'approved'),
(174, 5, '2026-05-05', '2026-05-05 07:00:00', '2026-05-05 16:00:00', 0, 'approved'),
(175, 5, '2026-05-06', '2026-05-06 07:00:00', '2026-05-06 16:00:00', 0, 'approved'),
(176, 5, '2026-05-07', '2026-05-07 07:00:00', '2026-05-07 16:00:00', 0, 'approved'),
(177, 5, '2026-05-08', '2026-05-08 07:00:00', '2026-05-08 16:00:00', 0, 'approved'),
(178, 5, '2026-05-09', '2026-05-09 07:00:00', '2026-05-09 16:00:00', 0, 'approved'),
(179, 5, '2026-05-10', '2026-05-10 07:00:00', '2026-05-10 16:00:00', 0, 'approved'),
(180, 5, '2026-05-11', '2026-05-11 07:00:00', '2026-05-11 16:00:00', 0, 'approved'),
(181, 5, '2026-05-12', '2026-05-12 07:00:00', '2026-05-12 16:00:00', 0, 'approved'),
(182, 5, '2026-05-13', '2026-05-13 07:00:00', '2026-05-13 16:00:00', 0, 'approved'),
(183, 5, '2026-05-14', '2026-05-14 07:00:00', '2026-05-14 16:00:00', 0, 'approved'),
(184, 5, '2026-05-15', '2026-05-15 07:00:00', '2026-05-15 16:00:00', 0, 'approved'),
(185, 5, '2026-05-16', '2026-05-16 07:00:00', '2026-05-16 16:00:00', 0, 'approved'),
(186, 5, '2026-05-17', '2026-05-17 07:00:00', '2026-05-17 16:00:00', 0, 'approved'),
(187, 5, '2026-05-18', '2026-05-18 07:00:00', '2026-05-18 16:00:00', 0, 'approved'),
(188, 5, '2026-05-19', '2026-05-19 07:00:00', '2026-05-19 16:00:00', 0, 'approved'),
(189, 5, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', 0, 'approved'),
(190, 2, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', 0, 'approved'),
(191, 2, '2026-05-02', '2026-05-02 09:00:00', '2026-05-02 18:00:00', 0, 'approved'),
(192, 2, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 18:00:00', 0, 'approved'),
(193, 2, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', 0, 'approved'),
(194, 2, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', 0, 'approved'),
(195, 2, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', 0, 'approved'),
(196, 2, '2026-05-07', '2026-05-07 09:00:00', '2026-05-07 18:00:00', 0, 'approved'),
(197, 2, '2026-05-08', '2026-05-08 09:00:00', '2026-05-08 18:00:00', 0, 'approved'),
(198, 2, '2026-05-09', '2026-05-09 09:00:00', '2026-05-09 18:00:00', 0, 'approved'),
(199, 2, '2026-05-10', '2026-05-10 09:00:00', '2026-05-10 18:00:00', 0, 'approved'),
(200, 2, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', 0, 'approved'),
(201, 2, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', 0, 'approved'),
(202, 2, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', 0, 'approved'),
(203, 2, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', 0, 'approved'),
(204, 2, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', 0, 'approved'),
(205, 2, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', 0, 'approved'),
(206, 2, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', 0, 'approved'),
(207, 2, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', 0, 'approved'),
(208, 2, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', 0, 'approved'),
(209, 2, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', 0, 'approved'),
(210, 5, '2026-05-02', '2026-05-02 07:00:00', '2026-05-02 16:00:00', 0, 'approved'),
(211, 5, '2026-05-03', '2026-05-03 07:00:00', '2026-05-03 16:00:00', 0, 'approved'),
(212, 5, '2026-05-04', '2026-05-04 07:00:00', '2026-05-04 16:00:00', 0, 'approved'),
(213, 5, '2026-05-05', '2026-05-05 07:00:00', '2026-05-05 16:00:00', 0, 'approved'),
(214, 5, '2026-05-06', '2026-05-06 07:00:00', '2026-05-06 16:00:00', 0, 'approved'),
(215, 5, '2026-05-07', '2026-05-07 07:00:00', '2026-05-07 16:00:00', 0, 'approved'),
(216, 5, '2026-05-08', '2026-05-08 07:00:00', '2026-05-08 16:00:00', 0, 'approved'),
(217, 5, '2026-05-09', '2026-05-09 07:00:00', '2026-05-09 16:00:00', 0, 'approved'),
(218, 5, '2026-05-10', '2026-05-10 07:00:00', '2026-05-10 16:00:00', 0, 'approved'),
(219, 5, '2026-05-11', '2026-05-11 07:00:00', '2026-05-11 16:00:00', 0, 'approved'),
(220, 5, '2026-05-12', '2026-05-12 07:00:00', '2026-05-12 16:00:00', 0, 'approved'),
(221, 5, '2026-05-13', '2026-05-13 07:00:00', '2026-05-13 16:00:00', 0, 'approved'),
(222, 5, '2026-05-14', '2026-05-14 07:00:00', '2026-05-14 16:00:00', 0, 'approved'),
(223, 5, '2026-05-15', '2026-05-15 07:00:00', '2026-05-15 16:00:00', 0, 'approved'),
(224, 5, '2026-05-16', '2026-05-16 07:00:00', '2026-05-16 16:00:00', 0, 'approved'),
(225, 5, '2026-05-17', '2026-05-17 07:00:00', '2026-05-17 16:00:00', 0, 'approved'),
(226, 5, '2026-05-18', '2026-05-18 07:00:00', '2026-05-18 16:00:00', 0, 'approved'),
(227, 5, '2026-05-19', '2026-05-19 07:00:00', '2026-05-19 16:00:00', 0, 'approved'),
(228, 5, '2026-05-20', '2026-05-20 07:00:00', '2026-05-20 16:00:00', 0, 'approved'),
(229, 12, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 'approved'),
(230, 5, '2026-05-02', '2026-05-02 09:00:00', '2026-05-02 16:00:00', 0, 'approved'),
(231, 5, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 16:00:00', 0, 'approved');

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
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_id` (`log_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=591;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `ob_requests`
--
ALTER TABLE `ob_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=232;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  ADD CONSTRAINT `log_edit_requests_ibfk_1` FOREIGN KEY (`log_id`) REFERENCES `logs` (`id`);

--
-- Constraints for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  ADD CONSTRAINT `overtime_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
