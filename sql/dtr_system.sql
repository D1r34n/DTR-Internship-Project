-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 29, 2026 at 08:04 AM
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
(0, 22, 610, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', '2026-05-29 11:48:13', '2026-05-29 11:50:22', 2, 0, 550, 0, 0, 'incomplete', 'none', 0, '2026-05-29 03:48:13', '2026-05-29 03:50:53');

-- --------------------------------------------------------

--
-- Table structure for table `cutoffs`
--

CREATE TABLE `cutoffs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cutoffs`
--

INSERT INTO `cutoffs` (`id`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(3, '2026-02-02', '2026-02-17', '2026-05-21 03:41:36', '2026-05-21 03:41:36'),
(5, '2026-01-21', '2026-02-01', '2026-05-21 06:07:13', '2026-05-21 06:07:13'),
(7, '2026-05-01', '2026-05-24', '2026-05-21 08:22:41', '2026-05-22 03:31:30'),
(8, '2026-05-29', '2026-06-10', '2026-05-29 03:38:14', '2026-05-29 03:38:14');

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
(14, 'SOCMED', 'Social Media', 3, '2026-05-05 04:05:07', '#f529b4'),
(15, 'CSD', 'Customer Service Department', NULL, '2026-05-14 09:15:43', '#4e73df'),
(16, 'CSD-RCT', 'Customer Service Repair', 15, '2026-05-14 09:19:54', '#4e73df');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `employee_id` char(6) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT 'HSN.123',
  `role_id` int(11) NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `hired_date` date NOT NULL,
  `tenure` int(11) DEFAULT 0,
  `birthdate` date DEFAULT NULL,
  `employement_status` enum('active','resigned') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `profile_image`, `first_name`, `last_name`, `email`, `password`, `role_id`, `department_id`, `hired_date`, `tenure`, `birthdate`, `employement_status`) VALUES
(2, '000002', 'avatar_user_2.png', 'User', 'Employee', 'user@gmail.com', 'user123', 5, 2, '2026-05-11', 0, '2000-01-01', 'active'),
(3, '000001', 'avatar_user_3.png', 'User', 'Admin', 'admin@gmail.com', 'admin123', 3, 7, '2026-05-11', 0, '2000-01-01', 'active'),
(4, '000003', NULL, 'User', 'Workforce', 'user1@gmail.com', 'user123', 4, 14, '2026-05-11', 0, '2000-01-01', 'active'),
(7, '000004', 'avatar_user_7.png', 'Jignesh', 'Nate', 'jigs@gmail.com', 'jigs123', 5, 13, '2026-05-11', 0, '2000-05-25', 'active'),
(8, '000005', 'avatar_user_8.png', 'Justine', 'Tandoc', 'justine@gmail.com', '123', 5, 13, '2026-05-11', 0, '2000-05-11', 'active'),
(12, '000007', 'avatar_user_12.png', 'Dirk', 'Adolf Del Mundo', 'dirk@gmail.com', '123', 5, 13, '2026-05-11', 0, '2000-05-10', 'active'),
(15, '000010', NULL, 'Thonie', 'Revil', 'thonie.revil@hsnservice.com', '123', 2, 10, '2026-05-11', 0, '2000-01-01', 'active'),
(19, '000009', 'avatar_user_19.png', 'Edrian', 'Evangelista', 'edrian.evangelista@gmail.com', '123', 4, 10, '2026-05-11', 0, '2000-05-02', 'active'),
(20, '000006', 'avatar_user_20.png', 'Dharmveer', 'Sandhu', 'dharm@gmail.com', 'dharm123', 5, 10, '2026-05-11', 0, '2000-05-03', 'active'),
(22, '000008', 'avatar_user_22.png', 'Earl', 'David Jordan', 'earl3jordan@gmail.com', '123', 5, 10, '2026-05-11', 0, '2000-05-01', 'active'),
(23, '000014', NULL, 'Ranica', 'Jalotjot', 'ranica.jalotjot@hsnervice.com', '123', 2, 13, '2026-05-11', 0, '2000-01-01', 'active'),
(25, '000011', NULL, 'Erlyn', 'Dionisio', 'erlyn.dionisio@hsnervice.com', '123', 5, 13, '2026-05-11', 0, '2000-01-01', 'active'),
(37, '000013', 'default_profile.png', 'Gerome', 'Dy', 'gerome.dy@hsnservice.com', 'HSN.123', 5, 15, '2026-05-14', 0, '1998-05-01', 'active'),
(39, '000000', 'avatar_user_39.png', 'Super', 'Admin', 'superadmin@gmail.com', 'superadmin123', 1, NULL, '2026-05-21', 0, '2026-05-21', 'active'),
(40, '000067', 'default_profile.png', 'Ken', 'Jervic Dusaran', 'ken@gmail.com', 'HSN.123', 4, 12, '2026-05-28', 0, '2026-05-28', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balances`
--

CREATE TABLE `employee_leave_balances` (
  `employee_id` int(11) NOT NULL,
  `buffer_leave` int(11) DEFAULT 0,
  `total_buffer_leave` int(11) DEFAULT 0,
  `vacation_leave` decimal(5,2) DEFAULT 0.00,
  `total_vacation_leave` decimal(5,2) DEFAULT 0.00,
  `sick_leave` int(11) DEFAULT 4,
  `paternity_leave` int(11) DEFAULT 7,
  `maternity_leave` int(11) DEFAULT 90,
  `solo_parent_leave` int(11) DEFAULT 1,
  `birthday_leave` int(11) DEFAULT 1,
  `last_vacation_accrual` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_leave_balances`
--

INSERT INTO `employee_leave_balances` (`employee_id`, `buffer_leave`, `total_buffer_leave`, `vacation_leave`, `total_vacation_leave`, `sick_leave`, `paternity_leave`, `maternity_leave`, `solo_parent_leave`, `birthday_leave`, `last_vacation_accrual`, `created_at`, `updated_at`) VALUES
(2, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(3, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(4, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(7, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(8, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(12, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(15, 5, 5, 10, 10, 4, 7, 0, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 09:22:01'),
(19, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 09:05:02'),
(20, 5, 5, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-28 09:01:39'),
(22, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(23, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-15', '2026-05-15 01:15:26', '2026-05-15 01:15:26'),
(25, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-15', '2026-05-15 01:15:26', '2026-05-15 01:15:26'),
(37, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-15', '2026-05-15 01:15:26', '2026-05-15 01:15:26'),
(39, 0, 0, 0, 0, 4, 7, 90, 1, 1, '0000-00-00', '2026-05-21 07:32:23', '2026-05-21 07:32:23'),
(40, 0, 0, 0, 0, 4, 7, 90, 1, 1, '2026-05-28', '2026-05-28 08:23:39', '2026-05-28 08:23:39');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('holiday','party','meeting','announcement','other') DEFAULT 'other',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `color` varchar(20) DEFAULT '#0d6efd',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_type`, `start_datetime`, `end_datetime`, `color`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Xmas Party', 'Christmas Theme Wear', 'party', '2026-05-15 00:00:00', '2026-05-15 00:00:00', '#ec4899', 3, '2026-05-14 07:05:32', NULL),
(2, 'iaidbwqibhasbciuwe', 'sacvucvwdiucnwd', 'other', '2026-05-15 00:00:00', '2026-05-15 00:00:00', '#6b7280', 3, '2026-05-14 07:09:48', NULL),
(3, 'WALANG PASOK', 'BATO ESCAPE DAY', 'holiday', '2026-05-15 00:00:00', '2026-05-15 00:00:00', '#ef4444', 3, '2026-05-14 07:38:22', NULL),
(4, 'Meeting', 'Meeting', 'meeting', '2026-05-12 00:00:00', '2026-05-12 00:00:00', '#3b82f6', 3, '2026-05-14 07:42:56', NULL),
(5, 'Halooweennn', 'haloween theme wear', 'party', '2026-05-16 00:00:00', '2026-05-16 00:00:00', '#ec4899', 3, '2026-05-14 08:44:32', NULL),
(6, 'Jakos Burger', 'Eat After Lunch', 'party', '2026-05-22 00:00:00', NULL, '#ec4899', 39, '2026-05-22 06:00:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `selected_dates` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `selected_dates`, `reason`, `status`, `created_at`) VALUES
(17, 15, 2, '2026-05-18', '2026-05-18', '[\"2026-05-18\"]', 'vacation', 'approved', '2026-05-07 09:14:43'),
(18, 25, 3, '2026-05-11', '2026-05-11', '[\"2026-05-11\"]', 'Birthday', 'approved', '2026-05-11 09:51:41'),
(19, 25, 5, '2026-05-11', '2026-05-11', '[\"2026-05-11\"]', ' [Client: Acer Ortigas] Meeting', 'approved', '2026-05-12 02:57:58'),
(20, 19, 5, '2026-05-12', '2026-05-12', '[\"2026-05-12\"]', 'Meeting', 'approved', '2026-05-12 03:09:35'),
(21, 22, 2, '2026-05-15', '2026-05-15', '[\"2026-05-15\"]', 'bounce nko', 'approved', '2026-05-14 09:05:11'),
(22, 15, 5, '2026-05-14', '2026-05-14', '[\"2026-05-14\"]', 'meeting', 'approved', '2026-05-14 09:21:56'),
(23, 22, 5, '2026-05-21', '2026-05-21', '[\"2026-05-21\"]', 'Meteng', 'approved', '2026-05-20 01:51:08'),
(24, 22, 3, '2026-05-20', '2026-05-20', '[\"2026-05-20\"]', 'bday', 'approved', '2026-05-20 02:55:35'),
(25, 15, 5, '2026-05-20', '2026-05-20', '[\"2026-05-20\"]', 'Meeting', 'approved', '2026-05-21 08:28:43');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `max_days` int(11) NOT NULL DEFAULT 1,
  `direction` enum('past','future','any') NOT NULL DEFAULT 'any',
  `description_label` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `name`, `label`, `max_days`, `direction`, `description_label`, `is_active`, `sort_order`) VALUES
(1, 'sick leave', 'Sick Leave', 4, 'past', 'up to 4 past dates only (before today)', 1, 1),
(2, 'vacation leave', 'Vacation Leave', 999, 'future', 'future dates only', 1, 2),
(3, 'birthday leave', 'Birthday Leave', 1, 'any', '1 day only', 1, 3),
(4, 'solo parent leave', 'Solo Parent Leave', 2, 'any', 'up to 2 days', 1, 4),
(5, 'ob leave', 'OB Leave', 1, 'any', 'official business leave', 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `schedule_id` bigint(20) UNSIGNED DEFAULT NULL,
  `log_type` enum('IN','OUT','BREAK_IN','BREAK_OUT','ADD_EMPLOYEE','EDIT_EMPLOYEE','DELETE_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE','DELETE_SCHEDULE') DEFAULT NULL,
  `log_time` datetime NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `accuracy` float DEFAULT NULL,
  `is_within_office` tinyint(1) NOT NULL DEFAULT 0,
  `distance_meters` float DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `original_log_time` datetime DEFAULT NULL,
  `schedule_request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `edit_reason` text DEFAULT NULL,
  `edit_requested_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `employee_id`, `schedule_id`, `log_type`, `log_time`, `longitude`, `latitude`, `accuracy`, `is_within_office`, `distance_meters`, `photo_path`, `created_at`, `original_log_time`, `schedule_request_id`, `edit_reason`, `edit_requested_by`) VALUES
(904, 22, 610, 'IN', '2026-05-29 11:48:13', 120.9954910, 14.5842560, 55, 1, 22.966, 'cap_22_20260529_114813.jpg', '2026-05-29 03:48:13', NULL, NULL, NULL, NULL),
(905, 22, NULL, 'EDIT_SCHEDULE', '2026-05-29 11:49:28', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-05-29 03:49:28', NULL, 11, '2c25269e9c3e1845', 39),
(906, 22, 610, 'OUT', '2026-05-29 22:50:00', 120.9954929, 14.5842550, 55, 1, 22.9184, 'cap_22_20260529_115022.jpg', '2026-05-29 03:50:22', '2026-05-29 11:50:22', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `log_edit_requests`
--

CREATE TABLE `log_edit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `log_id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `original_log_time` datetime NOT NULL,
  `proposed_log_time` datetime NOT NULL,
  `reason` text NOT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_edit_requests`
--

INSERT INTO `log_edit_requests` (`id`, `log_id`, `employee_id`, `original_log_time`, `proposed_log_time`, `reason`, `requested_by`, `status`, `created_at`, `updated_at`) VALUES
(1, 891, 19, '2026-05-25 09:51:49', '2026-05-25 08:30:00', 'No reason provided', 39, 'approved', '2026-05-25 01:51:49', '2026-05-29 01:59:18'),
(2, 892, 39, '2026-05-25 10:23:29', '2026-05-25 07:23:00', 'No reason provided', 39, 'approved', '2026-05-25 02:23:29', '2026-05-29 01:59:18'),
(4, 896, 39, '2026-05-29 10:00:15', '2026-05-29 07:30:00', 'No reason provided', 39, 'approved', '2026-05-29 02:01:49', '2026-05-29 02:01:49'),
(5, 906, 22, '2026-05-29 11:50:22', '2026-05-29 22:50:00', 'No reason provided', 39, 'approved', '2026-05-29 03:50:53', '2026-05-29 03:50:53');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `overtime_requests`
--

INSERT INTO `overtime_requests` (`id`, `employee_id`, `date`, `time_in`, `time_out`, `reason`, `status`, `created_at`) VALUES
(17, 22, '2026-05-12', '17:30:00', '18:22:00', 'PLSSS BATO I NEED THIS', 'approved', '2026-05-12 08:24:42'),
(18, 22, '2026-05-19', '19:30:00', '20:00:00', 'OT pls', 'approved', '2026-05-20 07:20:09'),
(19, 22, '2026-05-22', '17:30:00', '18:00:00', 'I NEED THIS', 'approved', '2026-05-22 08:01:05');

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
-- Table structure for table `quote_of_the_day`
--

CREATE TABLE `quote_of_the_day` (
  `id` int(11) NOT NULL DEFAULT 1,
  `quote_text` text NOT NULL DEFAULT '',
  `quote_author` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quote_of_the_day`
--

INSERT INTO `quote_of_the_day` (`id`, `quote_text`, `quote_author`, `updated_at`) VALUES
(1, 'When life gives you lemons, make lemonsquare', 'Earl', '2026-05-21 03:24:22');

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
  `pending_delete` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `request_type` enum('added','edit','deleted') DEFAULT NULL,
  `batch_id` varchar(32) DEFAULT NULL,
  `orig_is_rest_day` tinyint(1) DEFAULT NULL,
  `orig_scheduled_start` datetime DEFAULT NULL,
  `orig_scheduled_end` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `pending_delete`, `updated_at`, `is_archived`, `request_type`, `batch_id`, `orig_is_rest_day`, `orig_scheduled_start`, `orig_scheduled_end`) VALUES
(610, 22, '2026-05-29', '2026-05-29 12:00:00', '2026-05-29 21:00:00', 0, 0, NULL, 0, 'edit', '2c25269e9c3e1845', 0, '2026-05-29 08:30:00', '2026-05-29 17:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_edit_requests`
--

CREATE TABLE `schedule_edit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` varchar(32) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `reason` text NOT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule_edit_requests`
--

INSERT INTO `schedule_edit_requests` (`id`, `batch_id`, `employee_id`, `reason`, `requested_by`, `status`, `created_at`, `updated_at`) VALUES
(11, '2c25269e9c3e1845', 22, '', 39, 'approved', '2026-05-29 03:49:28', '2026-05-29 03:49:28');

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
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_date` (`work_date`),
  ADD KEY `idx_schedule_time` (`scheduled_start`,`scheduled_end`);

--
-- Indexes for table `cutoffs`
--
ALTER TABLE `cutoffs`
  ADD PRIMARY KEY (`id`);

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
  ADD UNIQUE KEY `uq_employee_id` (`employee_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `fk_employee_role` (`role_id`);

--
-- Indexes for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `fk_lr_leave_type` (`leave_type_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_leave_type_name` (`name`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_time` (`employee_id`,`log_time`),
  ADD KEY `idx_log_type` (`log_type`),
  ADD KEY `idx_schedule_id` (`schedule_id`);

--
-- Indexes for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ler_log_id` (`log_id`),
  ADD KEY `idx_ler_employee_id` (`employee_id`);

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
-- Indexes for table `quote_of_the_day`
--
ALTER TABLE `quote_of_the_day`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedule_edit_requests`
--
ALTER TABLE `schedule_edit_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ser_batch_id` (`batch_id`);

--
-- Indexes for table `system_state`
--
ALTER TABLE `system_state`
  ADD PRIMARY KEY (`key_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cutoffs`
--
ALTER TABLE `cutoffs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=907;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `password_setup_tokens`
--
ALTER TABLE `password_setup_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=611;

--
-- AUTO_INCREMENT for table `schedule_edit_requests`
--
ALTER TABLE `schedule_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
-- Constraints for table `employee_leave_balances`
--
ALTER TABLE `employee_leave_balances`
  ADD CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_lr_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON UPDATE CASCADE,
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
