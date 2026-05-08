-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 10:21 AM
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
(143, 19, 282, '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:06:04'),
(144, 19, 283, '2026-05-02', '2026-05-02 08:30:00', '2026-05-02 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(145, 19, 284, '2026-05-03', '2026-05-03 08:30:00', '2026-05-03 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(146, 19, 285, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:06:04'),
(147, 19, 286, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:06:04'),
(148, 19, 287, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:06:04'),
(149, 19, 288, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:06:04'),
(150, 19, 289, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', '2026-05-08 16:06:04', '2026-05-08 16:06:09', 0, 456, 84, 0, 0, 'present', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:06:09'),
(151, 19, 290, '2026-05-09', '2026-05-09 08:30:00', '2026-05-09 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(152, 19, 291, '2026-05-10', '2026-05-10 08:30:00', '2026-05-10 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(153, 19, 292, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(154, 19, 293, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(155, 19, 294, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(156, 19, 295, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(157, 19, 296, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(158, 19, 297, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(159, 19, 298, '2026-05-17', '2026-05-17 08:30:00', '2026-05-17 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(160, 19, 299, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(161, 19, 300, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(162, 19, 301, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(163, 19, 302, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(164, 19, 303, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(165, 19, 304, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(166, 19, 305, '2026-05-24', '2026-05-24 08:30:00', '2026-05-24 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(167, 19, 306, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(168, 19, 307, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(169, 19, 308, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(170, 19, 309, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(171, 19, 310, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(172, 19, 311, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36');

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
(676, 3, 'IN', '2026-05-08 15:56:29', 120.9955033, 14.5842579, 55, 1, 21.9768, '2026-05-08 07:56:29'),
(677, 3, 'BREAK_IN', '2026-05-08 15:56:41', 120.9955033, 14.5842579, 55, 1, 21.98, '2026-05-08 07:56:41'),
(678, 19, 'IN', '2026-05-08 16:06:04', 120.9954961, 14.5842637, 55, 1, 21.9518, '2026-05-08 08:06:04'),
(679, 19, 'OUT', '2026-05-08 16:06:09', 120.9954961, 14.5842637, 55, 1, 21.9518, '2026-05-08 08:06:09');

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
(14, 19, 150, 679, '2026-05-08', '2026-05-08 16:06:04', 'time_out', NULL, '2026-05-08 18:00:00', 'misinput', 19, 'pending', '2026-05-08 08:06:48', '2026-05-08 08:06:48');

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
(282, 19, '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', 0, 'approved'),
(283, 19, '2026-05-02', '2026-05-02 08:30:00', '2026-05-02 17:30:00', 1, 'approved'),
(284, 19, '2026-05-03', '2026-05-03 08:30:00', '2026-05-03 17:30:00', 1, 'approved'),
(285, 19, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', 0, 'approved'),
(286, 19, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', 0, 'approved'),
(287, 19, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', 0, 'approved'),
(288, 19, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', 0, 'approved'),
(289, 19, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', 0, 'approved'),
(290, 19, '2026-05-09', '2026-05-09 08:30:00', '2026-05-09 17:30:00', 1, 'approved'),
(291, 19, '2026-05-10', '2026-05-10 08:30:00', '2026-05-10 17:30:00', 1, 'approved'),
(292, 19, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', 0, 'approved'),
(293, 19, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', 0, 'approved'),
(294, 19, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', 0, 'approved'),
(295, 19, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', 0, 'approved'),
(296, 19, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', 0, 'approved'),
(297, 19, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', 1, 'approved'),
(298, 19, '2026-05-17', '2026-05-17 08:30:00', '2026-05-17 17:30:00', 1, 'approved'),
(299, 19, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', 0, 'approved'),
(300, 19, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', 0, 'approved'),
(301, 19, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', 0, 'approved'),
(302, 19, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', 0, 'approved'),
(303, 19, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', 0, 'approved'),
(304, 19, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', 1, 'approved'),
(305, 19, '2026-05-24', '2026-05-24 08:30:00', '2026-05-24 17:30:00', 1, 'approved'),
(306, 19, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', 0, 'approved'),
(307, 19, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved'),
(308, 19, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', 0, 'approved'),
(309, 19, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', 0, 'approved'),
(310, 19, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', 0, 'approved'),
(311, 19, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', 1, 'approved');

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=680;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=312;

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
