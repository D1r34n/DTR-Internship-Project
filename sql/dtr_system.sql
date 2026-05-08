-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 08:46 AM
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
(117, 2, 261, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-07 09:12:58', '2026-05-08 02:21:52'),
(118, 15, 262, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:13:25'),
(119, 15, 263, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:13:25'),
(120, 15, 264, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:13:25'),
(121, 15, 265, '2026-05-07', '2026-05-07 09:00:00', '2026-05-07 18:00:00', '2026-05-07 17:10:37', '2026-05-07 17:11:02', 0, 490, 49, 0, 0, 'present', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:13:25'),
(122, 15, 266, '2026-05-08', '2026-05-08 09:00:00', '2026-05-08 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(123, 15, 267, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(124, 15, 268, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(125, 15, 269, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(126, 15, 270, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(127, 15, 271, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(128, 15, 272, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(129, 15, 273, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(130, 15, 274, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(131, 15, 275, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(132, 15, 276, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(133, 15, 277, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(134, 15, 278, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(135, 15, 279, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(136, 15, 280, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58'),
(137, 15, 281, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-07 09:12:58', '2026-05-07 09:12:58');

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
  `profile_image` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `profile_image`, `first_name`, `last_name`, `name`, `email`, `password`, `role_id`, `department_id`) VALUES
(2, NULL, 'User', 'Employee', '', 'user@gmail.com', 'user123', 5, 2),
(3, NULL, 'User', 'Admin', '', 'admin@gmail.com', 'admin123', 3, 7),
(4, NULL, 'User', 'Workforce', '', 'user1@gmail.com', 'user123', 4, 14),
(5, NULL, 'Earl David', 'Jordan', '', 'earl@gmail.com', '123', 5, 13),
(7, NULL, 'Jignesh', 'Nate', '', 'jigs@gmail.com', '123', 5, 13),
(8, NULL, 'Justine', 'Tandoc', '', 'justine@gmail.com', '123', 5, 13),
(11, NULL, 'Dharmveer', 'Sandhu', '', 'dharm@gmail.com', '123', 5, NULL),
(12, NULL, 'Dirk Adolf', 'Del Mundo', '', 'dirk@gmail.com', '123', 5, 13),
(15, NULL, 'Thonie', 'Revil', '', 'thonie.revil@hsnservice.com', '123', 1, 10),
(19, NULL, 'Edrian', 'Evangelista', '', 'edrian.evangelista@gmail.com', '123', 5, 10);

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
(17, 15, 'vacation leave', '2026-05-18', '2026-05-18', '[\"2026-05-18\"]', 'vacation', 'approved', '2026-05-07 09:14:43');

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
(608, 2, 'IN', '2026-05-08 11:38:18', 120.9955110, 14.5842501, 55, 1, 22.2045, '2026-05-08 03:38:18'),
(609, 2, 'BREAK_IN', '2026-05-08 11:38:25', 120.9955110, 14.5842501, 55, 1, 22.2, '2026-05-08 03:38:25'),
(610, 2, 'OUT', '2026-05-08 11:39:04', 120.9955022, 14.5842551, 55, 1, 22.2981, '2026-05-08 03:39:04'),
(611, 2, 'IN', '2026-05-08 11:47:01', 120.9955182, 14.5842446, 55, 1, 22.298, '2026-05-08 03:47:01'),
(612, 2, 'BREAK_IN', '2026-05-08 11:47:11', 120.9955182, 14.5842446, 55, 1, 22.3, '2026-05-08 03:47:11'),
(613, 2, 'OUT', '2026-05-08 11:47:21', 120.9955182, 14.5842446, 55, 1, 22.298, '2026-05-08 03:47:21'),
(614, 2, 'IN', '2026-05-08 11:47:28', 120.9955182, 14.5842446, 55, 1, 22.298, '2026-05-08 03:47:28'),
(615, 2, 'OUT', '2026-05-08 11:47:35', 120.9955182, 14.5842446, 55, 1, 22.298, '2026-05-08 03:47:35'),
(616, 2, 'IN', '2026-05-08 11:48:00', 120.9955087, 14.5842510, 55, 1, 22.2665, '2026-05-08 03:48:00'),
(617, 2, 'OUT', '2026-05-08 13:15:22', 120.9955052, 14.5842541, 55, 1, 22.1942, '2026-05-08 05:15:22'),
(618, 2, 'IN', '2026-05-08 13:18:27', 120.9955064, 14.5842534, 55, 1, 22.1832, '2026-05-08 05:18:27'),
(619, 2, 'OUT', '2026-05-08 13:18:33', 120.9955064, 14.5842534, 55, 1, 22.1832, '2026-05-08 05:18:33'),
(620, 2, 'IN', '2026-05-08 13:29:03', 120.9955019, 14.5842551, 55, 1, 22.319, '2026-05-08 05:29:03'),
(621, 2, 'OUT', '2026-05-08 13:29:10', 120.9955019, 14.5842551, 55, 1, 22.319, '2026-05-08 05:29:10'),
(622, 2, 'IN', '2026-05-08 14:22:22', 120.9955017, 14.5842576, 55, 1, 22.1099, '2026-05-08 06:22:22'),
(623, 2, 'OUT', '2026-05-08 14:27:24', 120.9955114, 14.5842481, 55, 1, 22.3631, '2026-05-08 06:27:24');

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

-- --------------------------------------------------------

--
-- Table structure for table `password_setup_tokens`
--

CREATE TABLE `password_setup_tokens` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_setup_tokens`
--

INSERT INTO `password_setup_tokens` (`id`, `employee_id`, `token`, `expires_at`, `used`) VALUES
(1, 16, '1c1c3a8b30c5338fc6659a5dfa34f706e3c20cf9703ee1155e48c580c4f2060b', '2026-05-10 09:15:42', 0),
(2, 17, 'f05ab34e4d0c1b037a940e1bd38be2545cbcac234cba3b605b223cbc358fb9df', '2026-05-10 09:24:49', 0),
(3, 18, '302ffe417dc0c06b680f3a1ae7114684a55e80d907398a275bf3ac6afe3bb9c4', '2026-05-10 09:29:14', 0),
(4, 19, '30f37718e5d4e6a728fcea8285f44e83b051be17aa9c6e3463313f3e25f0a76e', '2026-05-10 09:30:30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_key` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `role_key`) VALUES
(1, 'Super Admin', 'superadmin'),
(2, 'Manager', 'manager'),
(3, 'Admin', 'admin'),
(4, 'Workforce', 'workforce'),
(5, 'Employee', 'employee');

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
(261, 15, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', 0, 'approved'),
(262, 15, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', 0, 'approved'),
(263, 15, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', 0, 'approved'),
(264, 15, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', 0, 'approved'),
(265, 15, '2026-05-07', '2026-05-07 09:00:00', '2026-05-07 18:00:00', 0, 'approved'),
(266, 15, '2026-05-08', '2026-05-08 09:00:00', '2026-05-08 18:00:00', 0, 'approved'),
(267, 15, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', 0, 'approved'),
(268, 15, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', 0, 'approved'),
(269, 15, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', 0, 'approved'),
(270, 15, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', 0, 'approved'),
(271, 15, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', 0, 'approved'),
(272, 15, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', 0, 'approved'),
(273, 15, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', 0, 'approved'),
(274, 15, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', 0, 'approved'),
(275, 15, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', 0, 'approved'),
(276, 15, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', 0, 'approved'),
(277, 15, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', 0, 'approved'),
(278, 15, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', 0, 'approved'),
(279, 15, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', 0, 'approved'),
(280, 15, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', 0, 'approved'),
(281, 15, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', 0, 'approved');

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
  ADD KEY `department_id` (`department_id`),
  ADD KEY `fk_employee_role` (`role_id`);

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
-- Indexes for table `password_setup_tokens`
--
ALTER TABLE `password_setup_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`),
  ADD UNIQUE KEY `role_key` (`role_key`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=143;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=624;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- AUTO_INCREMENT for table `password_setup_tokens`
--
ALTER TABLE `password_setup_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=282;

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
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_employee_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

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
