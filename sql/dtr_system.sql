-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 17, 2026 at 07:38 AM
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
(1, 2, 3785, '2026-06-15', '2026-06-15 13:30:00', '2026-06-15 22:30:00', '2026-06-15 13:20:30', '2026-06-15 13:21:52', 1, 0, 548, 0, 0, 'present', 'none', 0, '2026-06-15 05:20:30', '2026-06-15 05:21:52');

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
(1, 'CSD', 'Customer Service Department', NULL, '2025-06-09 16:00:00', '#4e73df'),
(3, 'LOG', 'Logistics', NULL, '2026-06-10 06:03:23', '#5babcd'),
(4, 'FIN', 'Finance', NULL, '2026-06-10 06:04:00', '#2ea836'),
(5, 'CC', 'Call Center', NULL, '2026-06-10 06:04:26', '#4e73df'),
(6, 'APHI', 'Acer Philippines', 5, '2026-06-11 01:21:41', '#f4103e'),
(7, 'AUSA', 'Acer USA', 5, '2026-06-11 01:22:03', '#00a1d6'),
(8, 'HR', 'Human Resources', NULL, '2026-06-11 01:22:44', '#f8f244');

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
  `employement_status` enum('active','resigned') NOT NULL DEFAULT 'active',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `profile_image`, `first_name`, `last_name`, `email`, `password`, `role_id`, `department_id`, `hired_date`, `tenure`, `birthdate`, `employement_status`, `is_archived`) VALUES
(2, '000001', 'default_profile.png', 'Thonie', 'Revil', 'thonie.revil@hsnservice.com', '123', 1, 1, '2026-06-10', 0, '2000-01-01', 'active', 0),
(89, '000002', 'default_profile.png', 'Earl David', 'Jordan', 'earl3jordan@gmail.com', 'Demecracked', 3, 1, '2026-06-11', 0, '2003-12-20', 'active', 0),
(105, '000003', 'default_profile.png', 'Edrian', 'Evangelista', 'edrian.evangelista@gmail.com', 'edrian123', 2, 3, '2026-06-11', 0, '2004-08-14', 'active', 0),
(106, '000004', 'default_profile.png', 'Jigs', 'Nate', 'jigs@gmail.com', 'HSN.123', 4, 3, '2026-06-11', 0, '2003-05-25', 'active', 0),
(107, '000005', 'default_profile.png', 'Justine', 'Tandoc', 'jet@gmail.com', 'JET123', 5, NULL, '2026-06-11', 0, '2003-11-12', 'active', 0),
(108, '000006', 'default_profile.png', 'Dirk Adolf', 'Del Mundo', 'dirk@gmail.com', 'dirk123', 5, 3, '2026-06-11', 0, '2003-04-19', 'active', 0),
(109, '000007', 'default_profile.png', 'Ken Jervic', 'Dusaran', 'ken@gmail.com', 'HSN.123', 5, 6, '2026-06-11', 0, '2004-10-04', 'active', 0),
(110, '000008', 'default_profile.png', 'Dharmveer', 'Sandhu', 'dharm@gmail.com', 'HSN.123', 5, 7, '2026-06-11', 0, '2003-07-13', 'active', 0),
(111, '000009', 'default_profile.png', 'Joaquin', 'Pacis', 'joaquin@gmail.com', 'HSN.123', 5, NULL, '2026-06-11', 0, '2004-01-23', 'active', 0),
(112, '000010', 'default_profile.png', 'Adrienne', 'Peralta', 'adrienne@gmail.com', 'HSN.123', 5, 4, '2026-06-11', 0, '2004-10-22', 'active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balances`
--

CREATE TABLE `employee_leave_balances` (
  `employee_id` int(11) NOT NULL,
  `buffer_leave` int(11) DEFAULT 0,
  `total_buffer_leave` int(11) NOT NULL DEFAULT 0,
  `vacation_leave` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_vacation_leave` decimal(5,2) NOT NULL DEFAULT 0.00,
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
(89, 0, 0, 5.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 01:34:48', '2026-06-11 01:48:45'),
(105, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(106, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(107, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(108, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(109, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(110, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(111, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31'),
(112, 0, 0, 0.00, 0.00, 4, 7, 90, 1, 1, '2026-06-11', '2026-06-11 03:09:31', '2026-06-11 03:09:31');

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
(1, 'Manila Day', 'Manila Day Holiday', 'holiday', '2026-06-24 00:00:00', NULL, '#ef4444', 2, '2026-06-10 06:08:26', NULL),
(2, 'Independence Day', 'FREEDOM!!!!!', 'holiday', '2026-06-12 00:00:00', NULL, '#ef4444', 2, '2026-06-11 01:49:53', NULL);

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

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL,
  `employee_id` bigint(20) NOT NULL,
  `schedule_id` bigint(20) UNSIGNED DEFAULT NULL,
  `log_type` enum('IN','OUT','BREAK_IN','BREAK_OUT','ADD_EMPLOYEE','EDIT_EMPLOYEE','DELETE_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE','DELETE_SCHEDULE','ADD_DEPARTMENT','EDIT_DEPARTMENT','DELETE_DEPARTMENT','ADD_EVENT','EDIT_EVENT','DELETE_EVENT','REQUEST_CHANGE_SCHEDULE') DEFAULT NULL,
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
(154, 2, 3785, 'IN', '2026-06-15 13:20:30', 120.9956000, 14.5846000, 5445, 1, 20.6888, 'cap_2_20260615_132030.jpg', '2026-06-15 05:20:30', NULL, NULL, NULL, NULL),
(155, 2, 3785, 'OUT', '2026-06-15 13:21:52', 120.9956000, 14.5846000, 5445, 1, 20.6888, 'cap_2_20260615_132152.jpg', '2026-06-15 05:21:52', NULL, NULL, NULL, NULL),
(156, 89, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, '8117f7b8bdb41000', 2),
(157, 106, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, 'c7be03b21ea85380', 2),
(158, 107, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, 'fdfa25a37755dfc5', 2),
(159, 108, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, '1898c7118804f5de', 2),
(160, 109, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, 'dbd15e270d574508', 2),
(161, 110, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, '49688adcc65dfc96', 2),
(162, 111, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, '1fb7520c3771a44c', 2),
(163, 112, NULL, 'ADD_SCHEDULE', '2026-06-15 15:13:45', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-15 07:13:45', NULL, NULL, '45824ab6d96b697b', 2);

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

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `employee_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(9, 89, '77b835807ac14be7658261e615a0badd38ccb75958936138a8f43139d1b2af86', '2026-06-17 12:54:32', 1, '2026-06-17 11:54:32'),
(10, 105, '42c80692b2646a75b4bd43bbaa389fdabbaab65090eed35d0ff18231454615cf', '2026-06-17 12:58:28', 1, '2026-06-17 11:58:28');

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
(1, 'When lfe gives you lemon, make lemonsquare', '', '2026-06-11 01:50:38');

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
(3786, 89, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3787, 89, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3788, 89, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3789, 89, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3790, 89, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3791, 89, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3792, 89, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3793, 89, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3794, 89, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3795, 89, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3796, 89, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3797, 89, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3798, 89, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3799, 89, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3800, 89, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3801, 89, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3802, 89, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3803, 89, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3804, 89, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3805, 89, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3806, 89, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3807, 89, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3808, 89, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3809, 89, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3810, 89, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3811, 89, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3812, 89, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3813, 89, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3814, 89, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3815, 89, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3816, 89, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3817, 89, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3818, 89, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3819, 89, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3820, 89, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3821, 89, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3822, 89, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3823, 89, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3824, 89, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3825, 89, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3826, 89, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3827, 89, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3828, 89, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3829, 89, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3830, 89, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3831, 89, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3832, 89, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3833, 89, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3834, 89, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3835, 89, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3836, 89, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3837, 89, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3838, 89, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3839, 89, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3840, 89, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3841, 89, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3842, 89, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3843, 89, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3844, 89, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 1, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3845, 89, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3846, 89, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', '8117f7b8bdb41000', NULL, NULL, NULL),
(3847, 106, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3848, 106, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3849, 106, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3850, 106, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3851, 106, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3852, 106, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3853, 106, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3854, 106, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3855, 106, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3856, 106, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3857, 106, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3858, 106, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3859, 106, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3860, 106, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3861, 106, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3862, 106, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3863, 106, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3864, 106, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3865, 106, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3866, 106, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3867, 106, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3868, 106, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3869, 106, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3870, 106, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3871, 106, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3872, 106, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3873, 106, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3874, 106, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3875, 106, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3876, 106, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3877, 106, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3878, 106, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3879, 106, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3880, 106, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3881, 106, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3882, 106, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3883, 106, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3884, 106, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3885, 106, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3886, 106, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3887, 106, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3888, 106, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3889, 106, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3890, 106, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3891, 106, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3892, 106, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3893, 106, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3894, 106, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3895, 106, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3896, 106, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3897, 106, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3898, 106, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3899, 106, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3900, 106, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3901, 106, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3902, 106, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3903, 106, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3904, 106, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3905, 106, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3906, 106, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3907, 106, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', 'c7be03b21ea85380', NULL, NULL, NULL),
(3908, 107, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3909, 107, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3910, 107, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3911, 107, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3912, 107, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3913, 107, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3914, 107, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3915, 107, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3916, 107, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3917, 107, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3918, 107, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3919, 107, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3920, 107, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3921, 107, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3922, 107, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3923, 107, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3924, 107, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3925, 107, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3926, 107, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3927, 107, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3928, 107, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3929, 107, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3930, 107, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3931, 107, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3932, 107, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3933, 107, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3934, 107, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3935, 107, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3936, 107, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3937, 107, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3938, 107, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3939, 107, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3940, 107, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3941, 107, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3942, 107, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3943, 107, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3944, 107, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3945, 107, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3946, 107, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3947, 107, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3948, 107, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3949, 107, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3950, 107, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3951, 107, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3952, 107, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3953, 107, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3954, 107, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3955, 107, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3956, 107, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3957, 107, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3958, 107, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3959, 107, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3960, 107, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3961, 107, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3962, 107, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3963, 107, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3964, 107, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3965, 107, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3966, 107, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3967, 107, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3968, 107, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', 'fdfa25a37755dfc5', NULL, NULL, NULL),
(3969, 108, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3970, 108, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3971, 108, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3972, 108, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3973, 108, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3974, 108, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3975, 108, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3976, 108, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3977, 108, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3978, 108, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3979, 108, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3980, 108, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3981, 108, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3982, 108, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3983, 108, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3984, 108, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3985, 108, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3986, 108, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3987, 108, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3988, 108, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3989, 108, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3990, 108, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3991, 108, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3992, 108, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3993, 108, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3994, 108, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3995, 108, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3996, 108, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3997, 108, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3998, 108, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(3999, 108, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4000, 108, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4001, 108, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4002, 108, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4003, 108, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4004, 108, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4005, 108, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4006, 108, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4007, 108, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4008, 108, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4009, 108, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4010, 108, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4011, 108, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4012, 108, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4013, 108, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4014, 108, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4015, 108, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4016, 108, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4017, 108, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4018, 108, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4019, 108, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4020, 108, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4021, 108, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4022, 108, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4023, 108, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4024, 108, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4025, 108, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4026, 108, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4027, 108, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4028, 108, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4029, 108, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', '1898c7118804f5de', NULL, NULL, NULL),
(4030, 109, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4031, 109, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4032, 109, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4033, 109, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4034, 109, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4035, 109, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4036, 109, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4037, 109, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4038, 109, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4039, 109, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4040, 109, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4041, 109, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4042, 109, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4043, 109, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4044, 109, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4045, 109, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4046, 109, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4047, 109, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4048, 109, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4049, 109, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4050, 109, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4051, 109, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4052, 109, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4053, 109, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4054, 109, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4055, 109, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4056, 109, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4057, 109, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4058, 109, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4059, 109, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4060, 109, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4061, 109, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4062, 109, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4063, 109, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4064, 109, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4065, 109, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4066, 109, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4067, 109, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4068, 109, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4069, 109, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4070, 109, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4071, 109, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4072, 109, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4073, 109, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4074, 109, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4075, 109, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4076, 109, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4077, 109, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4078, 109, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4079, 109, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4080, 109, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4081, 109, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4082, 109, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4083, 109, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4084, 109, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4085, 109, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4086, 109, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4087, 109, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4088, 109, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4089, 109, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4090, 109, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', 'dbd15e270d574508', NULL, NULL, NULL),
(4091, 110, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4092, 110, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4093, 110, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4094, 110, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4095, 110, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4096, 110, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4097, 110, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4098, 110, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4099, 110, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4100, 110, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4101, 110, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4102, 110, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4103, 110, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4104, 110, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4105, 110, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4106, 110, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4107, 110, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4108, 110, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4109, 110, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4110, 110, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4111, 110, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4112, 110, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4113, 110, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4114, 110, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4115, 110, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4116, 110, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4117, 110, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4118, 110, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4119, 110, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4120, 110, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4121, 110, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4122, 110, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4123, 110, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4124, 110, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4125, 110, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4126, 110, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4127, 110, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4128, 110, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4129, 110, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4130, 110, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4131, 110, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4132, 110, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4133, 110, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4134, 110, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4135, 110, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4136, 110, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4137, 110, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4138, 110, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4139, 110, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4140, 110, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4141, 110, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4142, 110, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4143, 110, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4144, 110, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4145, 110, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4146, 110, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4147, 110, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4148, 110, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4149, 110, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4150, 110, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4151, 110, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', '49688adcc65dfc96', NULL, NULL, NULL),
(4152, 111, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4153, 111, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4154, 111, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4155, 111, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4156, 111, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4157, 111, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4158, 111, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4159, 111, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL);
INSERT INTO `schedules` (`id`, `employee_id`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `pending_delete`, `updated_at`, `is_archived`, `request_type`, `batch_id`, `orig_is_rest_day`, `orig_scheduled_start`, `orig_scheduled_end`) VALUES
(4160, 111, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4161, 111, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4162, 111, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4163, 111, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4164, 111, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4165, 111, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4166, 111, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4167, 111, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4168, 111, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4169, 111, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4170, 111, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4171, 111, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4172, 111, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4173, 111, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4174, 111, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4175, 111, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4176, 111, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4177, 111, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4178, 111, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4179, 111, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4180, 111, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4181, 111, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4182, 111, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4183, 111, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4184, 111, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4185, 111, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4186, 111, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4187, 111, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4188, 111, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4189, 111, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4190, 111, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4191, 111, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4192, 111, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4193, 111, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4194, 111, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4195, 111, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4196, 111, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4197, 111, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4198, 111, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4199, 111, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4200, 111, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4201, 111, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4202, 111, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4203, 111, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4204, 111, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4205, 111, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4206, 111, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4207, 111, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4208, 111, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4209, 111, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4210, 111, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4211, 111, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4212, 111, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', '1fb7520c3771a44c', NULL, NULL, NULL),
(4213, 112, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4214, 112, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4215, 112, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4216, 112, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4217, 112, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4218, 112, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4219, 112, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4220, 112, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4221, 112, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4222, 112, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4223, 112, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4224, 112, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4225, 112, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4226, 112, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4227, 112, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4228, 112, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4229, 112, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4230, 112, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4231, 112, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4232, 112, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4233, 112, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4234, 112, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4235, 112, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4236, 112, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4237, 112, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4238, 112, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4239, 112, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4240, 112, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4241, 112, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4242, 112, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4243, 112, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4244, 112, '2026-06-01', '2026-06-01 08:00:00', '2026-06-01 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4245, 112, '2026-06-02', '2026-06-02 08:00:00', '2026-06-02 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4246, 112, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4247, 112, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4248, 112, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4249, 112, '2026-06-06', '2026-06-06 08:00:00', '2026-06-06 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4250, 112, '2026-06-07', '2026-06-07 08:00:00', '2026-06-07 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4251, 112, '2026-06-08', '2026-06-08 08:00:00', '2026-06-08 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4252, 112, '2026-06-09', '2026-06-09 08:00:00', '2026-06-09 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4253, 112, '2026-06-10', '2026-06-10 08:00:00', '2026-06-10 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4254, 112, '2026-06-11', '2026-06-11 08:00:00', '2026-06-11 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4255, 112, '2026-06-12', '2026-06-12 08:00:00', '2026-06-12 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4256, 112, '2026-06-13', '2026-06-13 08:00:00', '2026-06-13 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4257, 112, '2026-06-14', '2026-06-14 08:00:00', '2026-06-14 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4258, 112, '2026-06-15', '2026-06-15 08:00:00', '2026-06-15 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4259, 112, '2026-06-16', '2026-06-16 08:00:00', '2026-06-16 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4260, 112, '2026-06-17', '2026-06-17 08:00:00', '2026-06-17 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4261, 112, '2026-06-18', '2026-06-18 08:00:00', '2026-06-18 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4262, 112, '2026-06-19', '2026-06-19 08:00:00', '2026-06-19 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4263, 112, '2026-06-20', '2026-06-20 08:00:00', '2026-06-20 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4264, 112, '2026-06-21', '2026-06-21 08:00:00', '2026-06-21 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4265, 112, '2026-06-22', '2026-06-22 08:00:00', '2026-06-22 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4266, 112, '2026-06-23', '2026-06-23 08:00:00', '2026-06-23 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4267, 112, '2026-06-24', '2026-06-24 08:00:00', '2026-06-24 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4268, 112, '2026-06-25', '2026-06-25 08:00:00', '2026-06-25 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4269, 112, '2026-06-26', '2026-06-26 08:00:00', '2026-06-26 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4270, 112, '2026-06-27', '2026-06-27 08:00:00', '2026-06-27 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4271, 112, '2026-06-28', '2026-06-28 08:00:00', '2026-06-28 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4272, 112, '2026-06-29', '2026-06-29 08:00:00', '2026-06-29 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL),
(4273, 112, '2026-06-30', '2026-06-30 08:00:00', '2026-06-30 17:00:00', 0, 0, NULL, 0, 'added', '45824ab6d96b697b', NULL, NULL, NULL);

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

INSERT INTO `schedule_edit_requests` (`id`, `batch_id`, `employee_id`, `reason`, `requested_by`, `status`, `created_at`, `updated_at`, `approved_by`) VALUES
(1, '8117f7b8bdb41000', 89, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(2, 'c7be03b21ea85380', 106, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(3, 'fdfa25a37755dfc5', 107, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(4, '1898c7118804f5de', 108, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(5, 'dbd15e270d574508', 109, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(6, '49688adcc65dfc96', 110, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(7, '1fb7520c3771a44c', 111, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL),
(8, '45824ab6d96b697b', 112, 'Bulk import', 2, 'approved', '2026-06-15 07:13:45', '2026-06-15 07:13:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_state`
--

CREATE TABLE `system_state` (
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_emp_schedule` (`employee_id`,`schedule_id`),
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
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cutoffs`
--
ALTER TABLE `cutoffs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4274;

--
-- AUTO_INCREMENT for table `schedule_edit_requests`
--
ALTER TABLE `schedule_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
