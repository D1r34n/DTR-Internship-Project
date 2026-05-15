-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2026 at 07:43 AM
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
(150, 19, 289, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', '2026-05-08 16:06:04', '2026-05-08 15:00:00', 0, 456, 150, 0, 0, 'present', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 09:00:40'),
(151, 19, 290, '2026-05-09', '2026-05-09 08:30:00', '2026-05-09 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(152, 19, 291, '2026-05-10', '2026-05-10 08:30:00', '2026-05-10 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(153, 19, 292, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-08 08:04:36', '2026-05-12 03:15:54'),
(154, 19, 293, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', '2026-05-12 11:15:54', NULL, 0, 165, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-12 03:15:54'),
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
(172, 19, 311, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-08 08:04:36', '2026-05-08 08:04:36'),
(189, 22, 319, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 08:43:00', '2026-05-11 08:43:00'),
(190, 22, 320, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 08:43:00', '2026-05-11 08:43:00'),
(191, 22, 321, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 08:43:00', '2026-05-11 08:43:00'),
(194, 20, 324, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:13:41', '2026-05-11 09:13:41'),
(195, 20, 325, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:13:41', '2026-05-11 09:13:41'),
(196, 20, 326, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:13:41', '2026-05-11 09:13:41'),
(197, 20, 327, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:13:41', '2026-05-11 09:13:41'),
(198, 20, 328, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:13:41', '2026-05-11 09:13:41'),
(199, 20, 329, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:13:41', '2026-05-11 09:13:41'),
(200, 20, 331, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(201, 20, 332, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(202, 20, 333, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(203, 20, 334, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(204, 20, 335, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(205, 20, 336, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(206, 20, 337, '2026-05-24', '2026-05-24 08:30:00', '2026-05-24 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(207, 20, 338, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(208, 20, 339, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(209, 20, 340, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(210, 20, 341, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(211, 20, 342, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(212, 20, 343, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(213, 20, 344, '2026-05-31', '2026-05-31 08:30:00', '2026-05-31 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:14:35', '2026-05-11 09:14:35'),
(214, 15, 345, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', '2026-05-11 17:23:40', '2026-05-11 17:24:52', 0, 503, 35, 0, 0, 'present', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:24:52'),
(215, 15, 346, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(216, 15, 347, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(217, 15, 348, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(218, 15, 349, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(219, 15, 350, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(220, 15, 351, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(221, 15, 352, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(222, 15, 353, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(223, 15, 354, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(224, 15, 355, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(225, 15, 356, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(226, 15, 357, '2026-05-23', '2026-05-23 09:00:00', '2026-05-23 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(227, 15, 358, '2026-05-24', '2026-05-24 09:00:00', '2026-05-24 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(228, 15, 359, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(229, 15, 360, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(230, 15, 361, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(231, 15, 362, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(232, 15, 363, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(233, 15, 364, '2026-05-30', '2026-05-30 09:00:00', '2026-05-30 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(234, 15, 365, '2026-05-31', '2026-05-31 09:00:00', '2026-05-31 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:20:49', '2026-05-11 09:20:49'),
(239, 25, 366, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:47:01'),
(240, 25, 367, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(241, 25, 368, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(242, 25, 369, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(243, 25, 370, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(244, 25, 371, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(246, 25, 373, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(247, 25, 374, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(248, 25, 375, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(249, 25, 376, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(250, 25, 377, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(253, 25, 380, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(254, 25, 381, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(255, 25, 382, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(256, 25, 383, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(257, 25, 384, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(258, 25, 385, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:43:13', '2026-05-11 09:43:13'),
(260, 25, 390, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-11 09:46:43', '2026-05-11 09:46:43'),
(269, 22, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-12 08:21:37', '2026-05-12 08:21:37'),
(270, 22, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', '2026-05-12 07:21:00', '2026-05-12 18:30:00', 669, 0, 0, 60, 0, 'present', 'approved', 0, '2026-05-12 08:21:37', '2026-05-15 03:13:28'),
(273, 22, 456, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-14 03:59:09', '2026-05-14 03:59:09'),
(275, 22, 458, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-15 03:01:57', '2026-05-15 03:01:57'),
(276, 22, 460, '2026-05-21', '2026-05-21 06:00:00', '2026-05-21 15:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-15 03:12:33', '2026-05-15 03:12:33');

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
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT 'HSN.123',
  `role_id` int(11) NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `hired_date` date NOT NULL,
  `tenure` int(11) DEFAULT 0,
  `birthdate` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `profile_image`, `first_name`, `last_name`, `email`, `password`, `role_id`, `department_id`, `hired_date`, `tenure`, `birthdate`) VALUES
(2, 'avatar_user_2.png', 'User', 'Employee', 'user@gmail.com', 'user123', 5, 2, '2026-05-11', 0, '2000-01-01'),
(3, 'avatar_user_3.png', 'User', 'Admin', 'admin@gmail.com', 'admin123', 3, 7, '2026-05-11', 0, '2000-01-01'),
(4, NULL, 'User', 'Workforce', 'user1@gmail.com', 'user123', 4, 14, '2026-05-11', 0, '2000-01-01'),
(7, 'avatar_user_7.png', 'Jignesh', 'Nate', 'jigs@gmail.com', 'HSN.123', 5, 13, '2026-05-11', 0, '2000-05-25'),
(8, 'avatar_user_8.png', 'Justine', 'Tandoc', 'justine@gmail.com', '123', 5, 13, '2026-05-11', 0, '2000-05-11'),
(12, 'avatar_user_12.png', 'Dirk', 'Adolf Del Mundo', 'dirk@gmail.com', '123', 5, 13, '2026-05-11', 0, '2000-05-10'),
(15, NULL, 'Thonie', 'Revil', 'thonie.revil@hsnservice.com', '123', 3, 10, '2026-05-11', 0, '2000-01-01'),
(19, 'avatar_user_19.png', 'Edrian', 'Evangelista', 'edrian.evangelista@gmail.com', '123', 4, 10, '2026-05-11', 0, '2000-05-02'),
(20, 'avatar_user_20.png', 'Dharmveer', 'Sandhu', 'dharm@gmail.com', 'dharm123', 5, 7, '2026-05-11', 0, '2000-05-03'),
(22, 'avatar_user_22.png', 'Earl', 'David Jordan', 'earl3jordan@gmail.com', '123', 5, 10, '2026-05-11', 0, '2000-05-01'),
(23, NULL, 'Ranica', 'Jalotjot', 'ranica.jalotjot@hsnervice.com', '123', 5, 13, '2026-05-11', 0, '2000-01-01'),
(25, NULL, 'Erlyn', 'Dionisio', 'erlyn.dionisio@hsnervice.com', '123', 5, 13, '2026-05-11', 0, '2000-01-01');

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
(22, 0, 0, 4, 7, 90, 1, 1, '2026-05-11', '2026-05-11 07:41:20', '2026-05-11 07:41:20');

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
(5, 'Halooweennn', 'haloween theme wear', 'party', '2026-05-16 00:00:00', '2026-05-16 00:00:00', '#ec4899', 3, '2026-05-14 08:44:32', NULL);

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
(20, 19, 'ob leave', '2026-05-12', '2026-05-12', '[\"2026-05-12\"]', 'Meeting', 'approved', '2026-05-12 03:09:35', 'Acer Ortigas');

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
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `employee_id`, `log_type`, `log_time`, `longitude`, `latitude`, `accuracy`, `is_within_office`, `distance_meters`, `photo_path`, `created_at`) VALUES
(828, 22, 'IN', '2026-05-12 07:21:00', 120.9955124, 14.5842482, 55, 1, 22.2953, 'cap_22_20260512_162137.jpg', '2026-05-12 08:21:37'),
(829, 22, 'BREAK_IN', '2026-05-12 12:00:00', 120.9954947, 14.5842564, 55, 1, 22.68, NULL, '2026-05-12 08:21:49'),
(830, 22, 'BREAK_OUT', '2026-05-12 13:07:00', 120.9954947, 14.5842564, 55, 1, 22.68, NULL, '2026-05-12 08:21:57'),
(831, 22, 'OUT', '2026-05-12 18:30:00', 120.9954947, 14.5842564, 55, 1, 22.6814, 'cap_22_20260512_162205.jpg', '2026-05-12 08:22:05');

-- --------------------------------------------------------

--
-- Table structure for table `log_edit_requests`
--

CREATE TABLE `log_edit_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_id` bigint(20) UNSIGNED DEFAULT NULL,
  `log_id` bigint(20) DEFAULT NULL,
  `work_date` date NOT NULL,
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

INSERT INTO `log_edit_requests` (`id`, `employee_id`, `attendance_id`, `log_id`, `work_date`, `request_type`, `requested_time_in`, `requested_time_out`, `reason`, `initiated_by_id`, `status`, `created_at`, `updated_at`) VALUES
(19, 22, 270, 828, '2026-05-12', 'time_in', '2026-05-12 07:21:00', NULL, 'No reason provided', 3, 'approved', '2026-05-12 08:23:02', '2026-05-12 08:23:02'),
(20, 22, 270, 831, '2026-05-12', 'time_out', NULL, '2026-05-12 18:22:00', 'No reason provided', 3, 'approved', '2026-05-12 08:24:19', '2026-05-12 08:24:19'),
(21, 22, 270, 830, '2026-05-12', '', NULL, NULL, 'No reason provided', 3, 'approved', '2026-05-12 09:07:38', '2026-05-12 09:07:38'),
(22, 22, 270, 829, '2026-05-12', '', NULL, NULL, 'No reason provided', 3, 'approved', '2026-05-12 09:07:53', '2026-05-12 09:07:53'),
(23, 22, 270, 831, '2026-05-12', 'time_out', NULL, '2026-05-12 18:22:00', 'vjhvjhhkhk', 19, 'approved', '2026-05-15 01:48:32', '2026-05-15 01:48:50'),
(24, 22, 270, 831, '2026-05-12', 'time_out', NULL, '2026-05-12 18:30:00', 'fix time', 19, 'approved', '2026-05-15 03:13:04', '2026-05-15 03:13:28');

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
(17, 22, '2026-05-12', '17:30:00', '18:22:00', 'PLSSS BATO I NEED THIS', 'approved', '2026-05-12 08:24:42');

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
  `requested_by` int(11) DEFAULT NULL,
  `schedule_date` date NOT NULL,
  `scheduled_start` datetime DEFAULT NULL,
  `scheduled_end` datetime DEFAULT NULL,
  `is_rest_day` tinyint(1) DEFAULT 0,
  `status` enum('approved','pending','rejected') DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `employee_id`, `requested_by`, `schedule_date`, `scheduled_start`, `scheduled_end`, `is_rest_day`, `status`) VALUES
(282, 19, NULL, '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', 0, 'approved'),
(283, 19, NULL, '2026-05-02', '2026-05-02 08:30:00', '2026-05-02 17:30:00', 1, 'approved'),
(284, 19, NULL, '2026-05-03', '2026-05-03 08:30:00', '2026-05-03 17:30:00', 1, 'approved'),
(285, 19, NULL, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', 0, 'approved'),
(286, 19, NULL, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', 0, 'approved'),
(287, 19, NULL, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', 0, 'approved'),
(288, 19, NULL, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', 0, 'approved'),
(289, 19, NULL, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', 0, 'approved'),
(290, 19, NULL, '2026-05-09', '2026-05-09 08:30:00', '2026-05-09 17:30:00', 1, 'approved'),
(291, 19, NULL, '2026-05-10', '2026-05-10 08:30:00', '2026-05-10 17:30:00', 1, 'approved'),
(292, 19, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', 0, 'approved'),
(293, 19, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', 0, 'approved'),
(294, 19, NULL, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', 0, 'approved'),
(295, 19, NULL, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', 0, 'approved'),
(296, 19, NULL, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', 0, 'approved'),
(297, 19, NULL, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', 1, 'approved'),
(298, 19, NULL, '2026-05-17', '2026-05-17 08:30:00', '2026-05-17 17:30:00', 1, 'approved'),
(299, 19, NULL, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', 0, 'approved'),
(300, 19, NULL, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', 0, 'approved'),
(301, 19, NULL, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', 0, 'approved'),
(302, 19, NULL, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', 0, 'approved'),
(303, 19, NULL, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', 0, 'approved'),
(304, 19, NULL, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', 1, 'approved'),
(305, 19, NULL, '2026-05-24', '2026-05-24 08:30:00', '2026-05-24 17:30:00', 1, 'approved'),
(306, 19, NULL, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', 0, 'approved'),
(307, 19, NULL, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved'),
(308, 19, NULL, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', 0, 'approved'),
(309, 19, NULL, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', 0, 'approved'),
(310, 19, NULL, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', 0, 'approved'),
(311, 19, NULL, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', 1, 'approved'),
(317, 22, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', 0, 'approved'),
(318, 22, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', 0, 'approved'),
(319, 22, NULL, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', 0, 'approved'),
(320, 22, NULL, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', 0, 'approved'),
(321, 22, NULL, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', 0, 'approved'),
(322, 22, NULL, '2026-05-16', NULL, NULL, 1, 'approved'),
(323, 22, NULL, '2026-05-17', NULL, NULL, 1, 'approved'),
(324, 20, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', 0, 'approved'),
(325, 20, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', 0, 'approved'),
(326, 20, NULL, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', 0, 'approved'),
(327, 20, NULL, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', 0, 'approved'),
(328, 20, NULL, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', 0, 'approved'),
(329, 20, NULL, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', 0, 'approved'),
(330, 20, NULL, '2026-05-17', NULL, NULL, 1, 'approved'),
(331, 20, NULL, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', 0, 'approved'),
(332, 20, NULL, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', 0, 'approved'),
(333, 20, NULL, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', 0, 'approved'),
(334, 20, NULL, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', 0, 'approved'),
(335, 20, NULL, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', 0, 'approved'),
(336, 20, NULL, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', 0, 'approved'),
(337, 20, NULL, '2026-05-24', '2026-05-24 08:30:00', '2026-05-24 17:30:00', 1, 'approved'),
(338, 20, NULL, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', 0, 'approved'),
(339, 20, NULL, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved'),
(340, 20, NULL, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', 0, 'approved'),
(341, 20, NULL, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', 0, 'approved'),
(342, 20, NULL, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', 0, 'approved'),
(343, 20, NULL, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', 0, 'approved'),
(344, 20, NULL, '2026-05-31', '2026-05-31 08:30:00', '2026-05-31 17:30:00', 1, 'approved'),
(345, 15, NULL, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', 0, 'approved'),
(346, 15, NULL, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', 0, 'approved'),
(347, 15, NULL, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', 0, 'approved'),
(348, 15, NULL, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', 0, 'approved'),
(349, 15, NULL, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', 0, 'approved'),
(350, 15, NULL, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', 1, 'approved'),
(351, 15, NULL, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', 1, 'approved'),
(352, 15, NULL, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', 0, 'approved'),
(353, 15, NULL, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', 0, 'approved'),
(354, 15, NULL, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', 0, 'approved'),
(355, 15, NULL, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', 0, 'approved'),
(356, 15, NULL, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', 0, 'approved'),
(357, 15, NULL, '2026-05-23', '2026-05-23 09:00:00', '2026-05-23 18:00:00', 1, 'approved'),
(358, 15, NULL, '2026-05-24', '2026-05-24 09:00:00', '2026-05-24 18:00:00', 1, 'approved'),
(359, 15, NULL, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', 0, 'approved'),
(360, 15, NULL, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', 0, 'approved'),
(361, 15, NULL, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', 0, 'approved'),
(362, 15, NULL, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', 0, 'approved'),
(363, 15, NULL, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', 0, 'approved'),
(364, 15, NULL, '2026-05-30', '2026-05-30 09:00:00', '2026-05-30 18:00:00', 1, 'approved'),
(365, 15, NULL, '2026-05-31', '2026-05-31 09:00:00', '2026-05-31 18:00:00', 1, 'approved'),
(366, 25, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', 0, 'approved'),
(367, 25, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', 0, 'approved'),
(368, 25, NULL, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', 0, 'approved'),
(369, 25, NULL, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', 0, 'approved'),
(370, 25, NULL, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', 0, 'approved'),
(371, 25, NULL, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', 1, 'approved'),
(373, 25, NULL, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', 0, 'approved'),
(374, 25, NULL, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', 0, 'approved'),
(375, 25, NULL, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', 0, 'approved'),
(376, 25, NULL, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', 0, 'approved'),
(377, 25, NULL, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', 0, 'approved'),
(380, 25, NULL, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', 0, 'approved'),
(381, 25, NULL, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved'),
(382, 25, NULL, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', 0, 'approved'),
(383, 25, NULL, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', 0, 'approved'),
(384, 25, NULL, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', 0, 'approved'),
(385, 25, NULL, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', 1, 'approved'),
(387, 25, NULL, '2026-05-17', NULL, NULL, 1, 'approved'),
(388, 25, NULL, '2026-05-24', NULL, NULL, 1, 'approved'),
(389, 25, NULL, '2026-05-31', NULL, NULL, 1, 'approved'),
(390, 25, NULL, '2026-05-23', '2026-05-23 08:30:00', '2026-05-23 17:30:00', 0, 'approved'),
(391, 12, NULL, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 'approved'),
(392, 12, NULL, '2026-05-02', '2026-05-02 08:00:00', '2026-05-02 17:00:00', 0, 'approved'),
(393, 12, NULL, '2026-05-03', '2026-05-03 08:00:00', '2026-05-03 17:00:00', 0, 'approved'),
(394, 12, NULL, '2026-05-04', '2026-05-04 08:00:00', '2026-05-04 17:00:00', 0, 'approved'),
(395, 12, NULL, '2026-05-05', '2026-05-05 08:00:00', '2026-05-05 17:00:00', 0, 'approved'),
(396, 12, NULL, '2026-05-06', '2026-05-06 08:00:00', '2026-05-06 17:00:00', 0, 'approved'),
(397, 12, NULL, '2026-05-07', '2026-05-07 08:00:00', '2026-05-07 17:00:00', 0, 'approved'),
(398, 12, NULL, '2026-05-08', '2026-05-08 08:00:00', '2026-05-08 17:00:00', 0, 'approved'),
(399, 12, NULL, '2026-05-09', '2026-05-09 08:00:00', '2026-05-09 17:00:00', 0, 'approved'),
(400, 12, NULL, '2026-05-10', '2026-05-10 08:00:00', '2026-05-10 17:00:00', 0, 'approved'),
(401, 12, NULL, '2026-05-11', '2026-05-11 08:00:00', '2026-05-11 17:00:00', 0, 'approved'),
(402, 12, NULL, '2026-05-12', '2026-05-12 08:00:00', '2026-05-12 17:00:00', 0, 'approved'),
(403, 12, NULL, '2026-05-13', '2026-05-13 08:00:00', '2026-05-13 17:00:00', 0, 'approved'),
(404, 12, NULL, '2026-05-14', '2026-05-14 08:00:00', '2026-05-14 17:00:00', 0, 'approved'),
(405, 12, NULL, '2026-05-15', '2026-05-15 08:00:00', '2026-05-15 17:00:00', 0, 'approved'),
(406, 12, NULL, '2026-05-16', '2026-05-16 08:00:00', '2026-05-16 17:00:00', 0, 'approved'),
(407, 12, NULL, '2026-05-17', '2026-05-17 08:00:00', '2026-05-17 17:00:00', 0, 'approved'),
(408, 12, NULL, '2026-05-18', '2026-05-18 08:00:00', '2026-05-18 17:00:00', 0, 'approved'),
(409, 12, NULL, '2026-05-19', '2026-05-19 08:00:00', '2026-05-19 17:00:00', 0, 'approved'),
(410, 12, NULL, '2026-05-20', '2026-05-20 08:00:00', '2026-05-20 17:00:00', 0, 'approved'),
(411, 12, NULL, '2026-05-21', '2026-05-21 08:00:00', '2026-05-21 17:00:00', 0, 'approved'),
(412, 12, NULL, '2026-05-22', '2026-05-22 08:00:00', '2026-05-22 17:00:00', 0, 'approved'),
(413, 12, NULL, '2026-05-23', '2026-05-23 08:00:00', '2026-05-23 17:00:00', 0, 'approved'),
(414, 12, NULL, '2026-05-24', '2026-05-24 08:00:00', '2026-05-24 17:00:00', 0, 'approved'),
(415, 12, NULL, '2026-05-25', '2026-05-25 08:00:00', '2026-05-25 17:00:00', 0, 'approved'),
(416, 12, NULL, '2026-05-26', '2026-05-26 08:00:00', '2026-05-26 17:00:00', 0, 'approved'),
(417, 12, NULL, '2026-05-27', '2026-05-27 08:00:00', '2026-05-27 17:00:00', 0, 'approved'),
(418, 12, NULL, '2026-05-28', '2026-05-28 08:00:00', '2026-05-28 17:00:00', 0, 'approved'),
(419, 12, NULL, '2026-05-29', '2026-05-29 08:00:00', '2026-05-29 17:00:00', 0, 'approved'),
(420, 12, NULL, '2026-05-30', '2026-05-30 08:00:00', '2026-05-30 17:00:00', 0, 'approved'),
(421, 12, NULL, '2026-05-31', '2026-05-31 08:00:00', '2026-05-31 17:00:00', 0, 'approved'),
(422, 8, NULL, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', 0, 'approved'),
(423, 8, NULL, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', 1, 'approved'),
(424, 8, NULL, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', 1, 'approved'),
(425, 8, NULL, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', 0, 'approved'),
(426, 8, NULL, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', 0, 'approved'),
(427, 8, NULL, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', 0, 'approved'),
(428, 8, NULL, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', 0, 'approved'),
(429, 8, NULL, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', 0, 'approved'),
(430, 8, NULL, '2026-05-23', '2026-05-23 09:00:00', '2026-05-23 18:00:00', 1, 'approved'),
(431, 8, NULL, '2026-05-24', '2026-05-24 09:00:00', '2026-05-24 18:00:00', 1, 'approved'),
(432, 8, NULL, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', 0, 'approved'),
(433, 8, NULL, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', 0, 'approved'),
(434, 8, NULL, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', 0, 'approved'),
(435, 8, NULL, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', 0, 'approved'),
(436, 8, NULL, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', 0, 'approved'),
(437, 8, NULL, '2026-05-30', '2026-05-30 09:00:00', '2026-05-30 18:00:00', 1, 'approved'),
(438, 8, NULL, '2026-05-31', '2026-05-31 09:00:00', '2026-05-31 18:00:00', 1, 'approved'),
(439, 8, NULL, '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 18:00:00', 0, 'approved'),
(440, 8, NULL, '2026-06-02', '2026-06-02 09:00:00', '2026-06-02 18:00:00', 0, 'approved'),
(441, 8, NULL, '2026-06-03', '2026-06-03 09:00:00', '2026-06-03 18:00:00', 0, 'approved'),
(442, 8, NULL, '2026-06-04', '2026-06-04 09:00:00', '2026-06-04 18:00:00', 0, 'approved'),
(443, 8, NULL, '2026-06-05', '2026-06-05 09:00:00', '2026-06-05 18:00:00', 0, 'approved'),
(444, 8, NULL, '2026-06-06', '2026-06-06 09:00:00', '2026-06-06 18:00:00', 1, 'approved'),
(445, 8, NULL, '2026-06-07', '2026-06-07 09:00:00', '2026-06-07 18:00:00', 1, 'approved'),
(446, 8, NULL, '2026-06-08', '2026-06-08 09:00:00', '2026-06-08 18:00:00', 0, 'approved'),
(447, 8, NULL, '2026-06-09', '2026-06-09 09:00:00', '2026-06-09 18:00:00', 0, 'approved'),
(448, 8, NULL, '2026-06-10', '2026-06-10 09:00:00', '2026-06-10 18:00:00', 0, 'approved'),
(449, 8, NULL, '2026-06-11', '2026-06-11 09:00:00', '2026-06-11 18:00:00', 0, 'approved'),
(450, 8, NULL, '2026-06-12', '2026-06-12 09:00:00', '2026-06-12 18:00:00', 0, 'approved'),
(451, 8, NULL, '2026-06-13', '2026-06-13 09:00:00', '2026-06-13 18:00:00', 1, 'approved'),
(452, 8, NULL, '2026-06-14', '2026-06-14 09:00:00', '2026-06-14 18:00:00', 1, 'approved'),
(453, 8, NULL, '2026-06-15', '2026-06-15 09:00:00', '2026-06-15 18:00:00', 0, 'approved'),
(454, 1001, NULL, '2026-05-01', '2026-05-01 08:00:00', '2026-05-01 17:00:00', 0, 'approved'),
(456, 22, NULL, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', 0, 'approved'),
(458, 22, 19, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', 0, 'approved'),
(459, 22, 19, '2026-05-20', NULL, NULL, 1, 'pending'),
(460, 22, 19, '2026-05-21', '2026-05-21 06:00:00', '2026-05-21 15:00:00', 0, 'approved');

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
  ADD KEY `idx_log_type` (`log_type`);

--
-- Indexes for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_id` (`log_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=277;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=832;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `overtime_requests`
--
ALTER TABLE `overtime_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=461;

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
