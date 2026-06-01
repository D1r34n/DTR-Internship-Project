-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2026 at 09:57 AM
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
(1, 22, 635, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-28 03:49:11', '2026-05-28 03:49:11'),
(2, 22, 630, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-28 03:49:11', '2026-05-28 03:49:11'),
(3, 22, 631, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-28 03:49:11', '2026-05-28 03:49:11'),
(13, 22, 633, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', '2026-05-28 15:16:37', '2026-05-28 15:17:02', 0, 406, 133, 0, 0, 'present', 'none', 0, '2026-05-28 07:16:37', '2026-05-28 07:17:02'),
(18, 20, 650, '2026-06-03', '2026-06-03 08:00:00', '2026-06-03 17:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 01:48:02', '2026-06-01 01:48:02'),
(19, 20, 651, '2026-06-04', '2026-06-04 08:00:00', '2026-06-04 17:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 01:48:02', '2026-06-01 01:48:02'),
(20, 20, 652, '2026-06-05', '2026-06-05 08:00:00', '2026-06-05 17:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 01:48:02', '2026-06-01 01:48:02'),
(24, 20, 664, '2026-06-02', '2026-06-02 19:30:00', '2026-06-03 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:14:12', '2026-06-01 06:14:12'),
(25, 20, 665, '2026-06-03', '2026-06-03 19:30:00', '2026-06-04 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:14:12', '2026-06-01 06:14:12'),
(26, 20, 666, '2026-06-04', '2026-06-04 19:30:00', '2026-06-05 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:14:12', '2026-06-01 06:14:12'),
(27, 20, 667, '2026-06-05', '2026-06-05 19:30:00', '2026-06-06 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:14:12', '2026-06-01 06:14:12'),
(28, 20, 663, '2026-06-01', '2026-06-01 15:00:00', '2026-06-02 00:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:15:30', '2026-06-01 06:15:30'),
(29, 19, 638, '2026-05-17', '2026-05-17 08:30:00', '2026-05-17 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(30, 19, 639, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(31, 19, 640, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(32, 19, 641, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(33, 19, 642, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(34, 19, 643, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(35, 19, 645, '2026-05-25', '2026-05-25 07:30:00', '2026-05-25 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(36, 19, 646, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(37, 19, 647, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(38, 19, 637, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-06-01 06:16:44', '2026-06-01 06:16:44'),
(40, 19, 671, '2026-06-02', '2026-06-02 15:00:00', '2026-06-03 00:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:22:27', '2026-06-01 06:22:27'),
(41, 19, 672, '2026-06-03', '2026-06-03 15:00:00', '2026-06-04 00:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:22:27', '2026-06-01 06:22:27'),
(42, 19, 673, '2026-06-04', '2026-06-04 15:00:00', '2026-06-05 00:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:22:27', '2026-06-01 06:22:27'),
(43, 19, 674, '2026-06-05', '2026-06-05 15:00:00', '2026-06-06 00:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:22:28', '2026-06-01 06:22:28'),
(46, 19, 670, '2026-06-01', '2026-06-01 15:00:00', '2026-06-02 00:00:00', '2026-06-01 14:54:54', NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-06-01 06:54:54', '2026-06-01 06:54:54');

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
(7, '2026-05-01', '2026-05-24', '2026-05-21 08:22:41', '2026-05-22 03:31:30');

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
  `employement_status` enum('active','resigned') NOT NULL DEFAULT 'active',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_id`, `profile_image`, `first_name`, `last_name`, `email`, `password`, `role_id`, `department_id`, `hired_date`, `tenure`, `birthdate`, `employement_status`, `is_archived`) VALUES
(2, '000002', 'avatar_user_2.png', 'User', 'Employee', 'user@gmail.com', 'user123', 5, 2, '2026-05-11', 0, '2000-01-01', 'active', 0),
(3, '000001', 'avatar_user_3.png', 'User', 'Admin', 'admin@gmail.com', 'admin123', 3, 7, '2026-05-11', 0, '2000-01-01', 'active', 0),
(4, '000003', NULL, 'User', 'Workforce', 'user1@gmail.com', 'user123', 4, 14, '2026-05-11', 0, '2000-01-01', 'active', 0),
(7, '000004', 'avatar_user_7.png', 'Jignesh', 'Nate', 'jigs@gmail.com', 'jigs123', 5, 13, '2026-05-11', 0, '2000-05-25', 'active', 0),
(8, '000005', 'avatar_user_8.png', 'Justine', 'Bieber', 'justine@gmail.com', '123', 5, 13, '2026-05-11', 0, '2000-05-11', 'active', 0),
(12, '000007', 'avatar_user_12.png', 'Dirk', 'Adolf Del Mundo', 'dirk@gmail.com', '123', 5, 13, '2026-05-11', 0, '2000-05-10', 'active', 0),
(15, '000010', NULL, 'Thonie', 'Revil', 'thonie.revil@hsnservice.com', '123', 2, 10, '2026-05-11', 0, '2000-01-01', 'active', 0),
(19, '000009', 'avatar_user_19.png', 'Edrian', 'Evangelista', 'edrian.evangelista@gmail.com', '123', 4, 10, '2026-05-11', 0, '2000-05-02', 'active', 0),
(20, '000006', 'avatar_user_20.png', 'Dharmveer', 'Sandhu', 'dharm@gmail.com', 'dharm123', 5, 10, '2026-05-11', 0, '2000-05-03', 'active', 0),
(22, '000008', 'avatar_user_22.png', 'Earl', 'David Jordan', 'earl3jordan@gmail.com', '123', 5, 10, '2026-05-11', 0, '2000-05-01', 'active', 0),
(23, '000014', NULL, 'Ranica', 'Jalotjot', 'ranica.jalotjot@hsnervice.com', '123', 2, 13, '2026-05-11', 0, '2000-01-01', 'active', 0),
(25, '000011', NULL, 'Erlyn', 'Dionisio', 'erlyn.dionisio@hsnervice.com', '123', 5, 13, '2026-05-11', 0, '2000-01-01', 'active', 0),
(37, '000013', 'default_profile.png', 'Gerome', 'Dy', 'gerome.dy@hsnservice.com', 'HSN.123', 5, 15, '2026-05-14', 0, '1998-05-01', 'active', 0),
(39, '000000', 'avatar_user_39.png', 'Super', 'Admin', 'superadmin@gmail.com', 'superadmin123', 1, NULL, '2026-05-21', 0, '2026-05-21', 'active', 0),
(47, '000067', 'default_profile.png', 'Ken Jervic', 'Dusaran', 'ken@gmail.com', 'HSN.123', 5, 10, '2026-05-29', 0, '2003-12-20', 'active', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employee_leave_balances`
--

CREATE TABLE `employee_leave_balances` (
  `employee_id` int(11) NOT NULL,
  `buffer_leave` int(11) DEFAULT 0,
  `vacation_leave` int(11) DEFAULT 0,
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

INSERT INTO `employee_leave_balances` (`employee_id`, `buffer_leave`, `vacation_leave`, `sick_leave`, `paternity_leave`, `maternity_leave`, `solo_parent_leave`, `birthday_leave`, `last_vacation_accrual`, `created_at`, `updated_at`) VALUES
(2, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(3, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(4, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(7, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(8, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(12, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(15, 5, 10, 4, 7, 0, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 09:22:01'),
(19, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 09:05:02'),
(20, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(22, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20'),
(23, 0, 0, 4, 7, 90, 1, 1, '2026-05-15', '2026-05-15 01:15:26', '2026-05-15 01:15:26'),
(25, 0, 0, 4, 7, 90, 1, 1, '2026-05-15', '2026-05-15 01:15:26', '2026-05-15 01:15:26'),
(37, 0, 0, 4, 7, 90, 1, 1, '2026-05-15', '2026-05-15 01:15:26', '2026-05-15 01:15:26'),
(39, 0, 0, 4, 7, 90, 1, 1, '0000-00-00', '2026-05-21 07:32:23', '2026-05-21 07:32:23'),
(47, 0, 0, 4, 7, 90, 1, 1, '2026-05-29', '2026-05-29 06:15:34', '2026-05-29 06:15:34');

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
  `leave_type` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `selected_dates` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `client_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type`, `start_date`, `end_date`, `selected_dates`, `reason`, `status`, `created_at`, `client_name`) VALUES
(17, 15, 'vacation leave', '2026-05-18', '2026-05-18', '[\"2026-05-18\"]', 'vacation', 'approved', '2026-05-07 09:14:43', NULL),
(18, 25, 'birthday leave', '2026-05-11', '2026-05-11', '[\"2026-05-11\"]', 'Birthday', 'approved', '2026-05-11 09:51:41', NULL),
(19, 25, 'ob leave', '2026-05-11', '2026-05-11', '[\"2026-05-11\"]', 'Meeting', 'approved', '2026-05-12 02:57:58', 'Acer'),
(20, 19, 'ob leave', '2026-05-12', '2026-05-12', '[\"2026-05-12\"]', 'Meeting', 'approved', '2026-05-12 03:09:35', 'Acer Ortigas'),
(21, 22, 'vacation leave', '2026-05-15', '2026-05-15', '[\"2026-05-15\"]', 'bounce nko', 'approved', '2026-05-14 09:05:11', NULL),
(22, 15, 'ob leave', '2026-05-14', '2026-05-14', '[\"2026-05-14\"]', 'meeting', 'approved', '2026-05-14 09:21:56', 'Acer'),
(23, 22, 'ob leave', '2026-05-21', '2026-05-21', '[\"2026-05-21\"]', 'Meteng', 'approved', '2026-05-20 01:51:08', 'Jigs'),
(24, 22, 'birthday leave', '2026-05-20', '2026-05-20', '[\"2026-05-20\"]', 'bday', 'approved', '2026-05-20 02:55:35', NULL),
(25, 15, 'ob leave', '2026-05-20', '2026-05-20', '[\"2026-05-20\"]', 'Meeting', 'approved', '2026-05-21 08:28:43', 'BPO - SF');

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
  `proposed_log_time` datetime DEFAULT NULL,
  `edit_status` enum('pending','approved','rejected') DEFAULT NULL,
  `edit_reason` text DEFAULT NULL,
  `edit_requested_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `employee_id`, `schedule_id`, `log_type`, `log_time`, `longitude`, `latitude`, `accuracy`, `is_within_office`, `distance_meters`, `photo_path`, `created_at`, `original_log_time`, `proposed_log_time`, `edit_status`, `edit_reason`, `edit_requested_by`) VALUES
(993, 20, NULL, 'ADD_SCHEDULE', '2026-06-01 14:13:48', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-01 06:13:48', NULL, NULL, 'approved', '12b51a4dda595875', 19),
(994, 20, NULL, 'ADD_SCHEDULE', '2026-06-01 14:15:17', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-01 06:15:17', NULL, NULL, 'approved', 'f1a8ecc827daa935', 19),
(997, 19, NULL, 'ADD_SCHEDULE', '2026-06-01 14:22:19', 0.0000000, 0.0000000, NULL, 0, NULL, NULL, '2026-06-01 06:22:19', NULL, NULL, 'approved', '64af51ccd602ef5f', 19),
(1002, 19, 670, 'IN', '2026-06-01 14:54:54', 120.9955065, 14.5842456, 55, 1, 22.8856, 'cap_19_20260601_145454.jpg', '2026-06-01 06:54:54', NULL, NULL, NULL, NULL, NULL);

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
(17, 22, '2026-05-12', '17:30:00', '18:22:00', 'PLSSS BATO I NEED THIS', 'approved', '2026-05-12 08:24:42'),
(18, 22, '2026-05-19', '19:30:00', '20:00:00', 'OT pls', 'approved', '2026-05-20 07:20:09'),
(19, 22, '2026-05-22', '17:30:00', '18:00:00', 'I NEED THIS', 'approved', '2026-05-22 08:01:05'),
(22, 19, '2026-05-25', '17:30:00', '19:00:00', 'Ot boss', 'approved', '2026-05-25 07:48:45');

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
  `requested_by` int(11) DEFAULT NULL,
  `schedule_date` date NOT NULL,
  `scheduled_start` datetime DEFAULT NULL,
  `scheduled_end` datetime DEFAULT NULL,
  `is_rest_day` tinyint(1) DEFAULT 0,
  `status` enum('approved','pending','rejected') DEFAULT 'approved',
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

INSERT INTO `schedules` (`id`, `employee_id`, `requested_by`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `status`, `pending_delete`, `updated_at`, `is_archived`, `request_type`, `batch_id`, `orig_is_rest_day`, `orig_scheduled_start`, `orig_scheduled_end`) VALUES
(599, 20, 39, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(600, 20, 39, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(601, 20, 39, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(602, 20, 39, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(603, 20, 39, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(604, 20, 39, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(605, 20, 39, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(606, 20, 39, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(607, 20, 39, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(608, 20, 39, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(609, 20, 39, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(610, 20, 39, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(611, 20, 39, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(612, 20, 39, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(613, 20, 39, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(614, 20, 39, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(615, 20, 39, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(616, 20, 39, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(617, 20, 39, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 18:00:00', 0, 'approved', 0, NULL, 0, 'edit', 'd8794b90e9387f37', 0, '2026-05-19 08:00:00', '2026-05-19 17:00:00'),
(618, 20, 39, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(619, 20, 39, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(620, 20, 39, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(621, 20, 39, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(622, 20, 39, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(623, 20, 39, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(628, 20, 39, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(629, 20, 39, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 1, 'approved', 0, NULL, 0, 'added', '8f91226615318f33', NULL, NULL, NULL),
(630, 22, 39, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved', 0, NULL, 0, 'added', '1ffebc9265823e82', NULL, NULL, NULL),
(635, 22, 19, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', 0, 'approved', 0, '2026-05-26 08:42:34', 0, 'added', 'b6c4ccc3f60cb8da', NULL, NULL, NULL),
(636, 22, 19, '2026-05-31', '2026-05-31 08:30:00', '2026-05-31 17:30:00', 0, 'approved', 0, '2026-05-28 02:25:29', 0, 'added', '5fed25fdb779ce54', NULL, NULL, NULL),
(637, 19, 39, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 18:00:00', 0, 'approved', 0, NULL, 0, 'edit', '3b49ee13eab98f19', 0, '2026-05-28 08:00:00', '2026-05-28 17:00:00'),
(638, 19, 39, '2026-05-17', '2026-05-17 08:30:00', '2026-05-17 17:30:00', 1, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 1, '2026-05-17 08:30:00', '2026-05-17 17:30:00'),
(639, 19, 39, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', 0, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 0, '2026-05-18 08:30:00', '2026-05-18 17:30:00'),
(640, 19, 39, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', 0, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 0, '2026-05-19 08:30:00', '2026-05-19 17:30:00'),
(641, 19, 39, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', 0, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 0, '2026-05-20 08:30:00', '2026-05-20 17:30:00'),
(642, 19, 39, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', 0, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 0, '2026-05-21 08:30:00', '2026-05-21 17:30:00'),
(643, 19, 39, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', 0, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 0, '2026-05-22 08:30:00', '2026-05-22 17:30:00'),
(644, 19, 39, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', 1, 'approved', 0, NULL, 0, 'edit', 'c6dc04b350e24264', 1, '2026-05-23 08:30:00', '2026-05-23 17:30:00'),
(645, 19, 39, '2026-05-25', '2026-05-25 07:30:00', '2026-05-25 17:30:00', 0, 'approved', 0, NULL, 0, 'edit', '2df33a449d231de3', 0, '2026-05-25 08:30:00', '2026-05-25 17:30:00'),
(646, 19, 39, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved', 0, NULL, 0, 'added', '8764b809646bb1c4', NULL, NULL, NULL),
(647, 19, 39, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', 0, 'approved', 0, NULL, 0, 'added', '8764b809646bb1c4', NULL, NULL, NULL),
(663, 20, 19, '2026-06-01', '2026-06-01 15:00:00', '2026-06-02 00:00:00', 0, 'approved', 0, '2026-06-01 06:15:30', 0, 'added', 'f1a8ecc827daa935', 0, '2026-06-01 08:00:00', '2026-06-01 17:00:00'),
(664, 20, 19, '2026-06-02', '2026-06-02 19:30:00', '2026-06-03 04:30:00', 0, 'approved', 0, '2026-06-01 06:14:12', 0, 'added', '12b51a4dda595875', 0, '2026-06-02 07:00:00', '2026-06-02 17:00:00'),
(665, 20, 19, '2026-06-03', '2026-06-03 19:30:00', '2026-06-04 04:30:00', 0, 'approved', 0, '2026-06-01 06:14:12', 0, 'added', '12b51a4dda595875', NULL, NULL, NULL),
(666, 20, 19, '2026-06-04', '2026-06-04 19:30:00', '2026-06-05 04:30:00', 0, 'approved', 0, '2026-06-01 06:14:12', 0, 'added', '12b51a4dda595875', NULL, NULL, NULL),
(667, 20, 19, '2026-06-05', '2026-06-05 19:30:00', '2026-06-06 04:30:00', 0, 'approved', 0, '2026-06-01 06:14:12', 0, 'added', '12b51a4dda595875', NULL, NULL, NULL),
(668, 20, 19, '2026-06-06', '2026-06-06 19:30:00', '2026-06-07 04:30:00', 1, 'approved', 0, '2026-06-01 06:14:12', 0, 'added', '12b51a4dda595875', NULL, NULL, NULL),
(669, 20, 19, '2026-06-07', '2026-06-07 19:30:00', '2026-06-08 04:30:00', 1, 'approved', 0, '2026-06-01 06:14:12', 0, 'added', '12b51a4dda595875', NULL, NULL, NULL),
(670, 19, 19, '2026-06-01', '2026-06-01 15:00:00', '2026-06-02 00:00:00', 0, 'approved', 0, '2026-06-01 06:22:27', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL),
(671, 19, 19, '2026-06-02', '2026-06-02 15:00:00', '2026-06-03 00:00:00', 0, 'approved', 0, '2026-06-01 06:22:27', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL),
(672, 19, 19, '2026-06-03', '2026-06-03 15:00:00', '2026-06-04 00:00:00', 0, 'approved', 0, '2026-06-01 06:22:27', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL),
(673, 19, 19, '2026-06-04', '2026-06-04 15:00:00', '2026-06-05 00:00:00', 0, 'approved', 0, '2026-06-01 06:22:27', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL),
(674, 19, 19, '2026-06-05', '2026-06-05 15:00:00', '2026-06-06 00:00:00', 0, 'approved', 0, '2026-06-01 06:22:27', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL),
(675, 19, 19, '2026-06-06', '2026-06-06 15:00:00', '2026-06-07 00:00:00', 1, 'approved', 0, '2026-06-01 06:22:28', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL),
(676, 19, 19, '2026-06-07', '2026-06-07 15:00:00', '2026-06-08 00:00:00', 1, 'approved', 0, '2026-06-01 06:22:28', 0, 'added', '64af51ccd602ef5f', NULL, NULL, NULL);

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
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employee_time` (`employee_id`,`log_time`),
  ADD KEY `idx_log_type` (`log_type`),
  ADD KEY `idx_schedule_id` (`schedule_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `cutoffs`
--
ALTER TABLE `cutoffs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

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
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1003;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=677;

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
