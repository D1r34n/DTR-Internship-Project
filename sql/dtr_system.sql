-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2026 at 11:14 AM
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
(318, 20, NULL, '2026-05-18', '2026-05-18 19:30:00', '2026-05-19 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 02:20:16', '2026-05-25 06:22:18'),
(319, 20, NULL, '2026-05-19', '2026-05-19 19:30:00', '2026-05-20 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 02:20:16', '2026-05-25 06:22:18'),
(320, 20, NULL, '2026-05-20', '2026-05-20 19:30:00', '2026-05-21 04:30:00', '2026-05-20 13:50:08', '2026-05-20 13:50:27', 0, 320, 220, 0, 0, 'present', 'none', 0, '2026-05-22 02:20:16', '2026-05-25 06:22:18'),
(321, 20, NULL, '2026-05-21', '2026-05-21 19:30:00', '2026-05-22 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 02:20:16', '2026-05-25 06:22:18'),
(331, 20, NULL, '2026-05-22', '2026-05-22 19:30:00', '2026-05-23 04:30:00', '2026-05-22 08:30:00', '2026-05-22 11:28:41', 3, 0, 361, 0, 0, 'present', 'none', 0, '2026-05-22 03:12:15', '2026-05-25 06:22:18'),
(341, 22, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(342, 22, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', '2026-05-12 07:21:00', '2026-05-12 18:22:00', 11, 0, 0, 52, 67, 'present', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(343, 22, NULL, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(344, 22, NULL, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(345, 22, NULL, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(346, 22, NULL, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(347, 22, NULL, '2026-05-19', '2026-05-19 10:30:00', '2026-05-19 19:30:00', '2026-05-19 10:21:59', '2026-05-19 20:00:00', 10, 0, 0, 30, 0, 'present', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(348, 22, NULL, '2026-05-20', '2026-05-20 11:00:00', '2026-05-20 20:00:00', '2026-05-20 09:21:06', '2026-05-20 17:30:00', 8, 0, 150, 0, 0, 'present', 'none', 0, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(349, 22, NULL, '2026-05-21', '2026-05-21 06:00:00', '2026-05-21 15:00:00', '2026-05-21 09:00:00', NULL, 0, 180, 0, 0, 0, 'incomplete', 'none', 1, '2026-05-22 06:13:31', '2026-05-22 06:13:31'),
(351, 19, NULL, '2026-05-01', '2026-05-01 08:30:00', '2026-05-01 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(352, 19, NULL, '2026-05-04', '2026-05-04 08:30:00', '2026-05-04 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(353, 19, NULL, '2026-05-05', '2026-05-05 08:30:00', '2026-05-05 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(354, 19, NULL, '2026-05-06', '2026-05-06 08:30:00', '2026-05-06 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(355, 19, NULL, '2026-05-07', '2026-05-07 08:30:00', '2026-05-07 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(356, 19, NULL, '2026-05-08', '2026-05-08 08:30:00', '2026-05-08 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(357, 19, NULL, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(358, 19, NULL, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(359, 19, NULL, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(360, 19, NULL, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(361, 19, NULL, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(362, 19, NULL, '2026-05-18', '2026-05-18 08:30:00', '2026-05-18 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(363, 19, NULL, '2026-05-19', '2026-05-19 08:30:00', '2026-05-19 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(364, 19, NULL, '2026-05-20', '2026-05-20 08:30:00', '2026-05-20 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(365, 19, NULL, '2026-05-21', '2026-05-21 08:30:00', '2026-05-21 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'absent', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(366, 19, NULL, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', '2026-05-22 15:12:15', NULL, 0, 402, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 07:12:15', '2026-05-22 07:12:15'),
(367, 22, 479, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', '2026-05-22 08:00:00', '2026-05-22 18:00:00', 10, 0, 0, 30, 0, 'present', 'approved', 0, '2026-05-22 07:42:44', '2026-05-25 06:01:44'),
(395, 20, 490, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 06:00:18', '2026-05-25 06:00:18'),
(396, 20, 491, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 06:00:18', '2026-05-25 06:00:18'),
(397, 20, 492, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 06:00:18', '2026-05-25 06:00:18'),
(398, 20, 493, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 06:00:18', '2026-05-25 06:00:18'),
(399, 20, 494, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 06:00:18', '2026-05-25 06:00:18'),
(404, 20, 532, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(405, 20, 502, '2026-05-23', '2026-05-23 19:30:00', '2026-05-24 04:30:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 06:13:15', '2026-05-25 06:23:25'),
(406, 20, 534, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(407, 20, 535, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(408, 20, 536, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(409, 20, 537, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(410, 20, 538, '2026-05-07', '2026-05-07 09:00:00', '2026-05-07 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(411, 20, 539, '2026-05-08', '2026-05-08 09:00:00', '2026-05-08 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(412, 20, 540, '2026-05-09', '2026-05-09 09:00:00', '2026-05-09 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(413, 20, 541, '2026-05-10', '2026-05-10 09:00:00', '2026-05-10 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(414, 20, 542, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(415, 20, 543, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(416, 20, 544, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(417, 20, 545, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(418, 20, 546, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(419, 20, 547, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(420, 20, 548, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(421, 20, 549, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(422, 20, 550, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(423, 20, 551, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(424, 20, 552, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(425, 20, 553, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(426, 20, 554, '2026-05-23', '2026-05-23 09:00:00', '2026-05-23 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(427, 20, 555, '2026-05-24', '2026-05-24 09:00:00', '2026-05-24 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(428, 20, 556, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(429, 20, 557, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(430, 20, 558, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(431, 20, 559, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(432, 20, 560, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(433, 20, 561, '2026-05-30', '2026-05-30 09:00:00', '2026-05-30 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(434, 20, 562, '2026-05-31', '2026-05-31 09:00:00', '2026-05-31 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28'),
(435, 22, 563, '2026-05-18', '2026-05-18 07:00:00', '2026-05-18 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(436, 22, 564, '2026-05-19', '2026-05-19 07:00:00', '2026-05-19 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(437, 22, 565, '2026-05-20', '2026-05-20 07:00:00', '2026-05-20 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(438, 22, 566, '2026-05-21', '2026-05-21 07:00:00', '2026-05-21 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(439, 22, 567, '2026-05-22', '2026-05-22 07:00:00', '2026-05-22 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(440, 22, 568, '2026-05-23', '2026-05-23 07:00:00', '2026-05-23 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(441, 22, 569, '2026-05-24', '2026-05-24 07:00:00', '2026-05-24 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(442, 22, 570, '2026-05-25', '2026-05-25 07:00:00', '2026-05-25 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(443, 22, 571, '2026-05-26', '2026-05-26 07:00:00', '2026-05-26 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(444, 22, 572, '2026-05-27', '2026-05-27 07:00:00', '2026-05-27 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(445, 22, 573, '2026-05-28', '2026-05-28 07:00:00', '2026-05-28 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(446, 22, 574, '2026-05-29', '2026-05-29 07:00:00', '2026-05-29 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(447, 22, 575, '2026-05-30', '2026-05-30 07:00:00', '2026-05-30 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(448, 22, 576, '2026-05-31', '2026-05-31 07:00:00', '2026-05-31 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:21:19', '2026-05-22 09:21:19'),
(449, 39, 577, '2026-05-27', '2026-05-27 07:00:00', '2026-05-27 16:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-25 02:45:57', '2026-05-25 02:45:57'),
(450, 20, 533, '2026-05-02', '2026-05-02 09:00:00', '2026-05-02 18:00:00', NULL, NULL, 0, 0, 0, 0, 0, 'incomplete', 'none', 0, '2026-05-22 09:19:28', '2026-05-22 09:19:28');

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
(39, '000000', 'avatar_user_39.png', 'Super', 'Admin', 'superadmin@gmail.com', 'superadmin123', 1, NULL, '2026-05-21', 0, '2026-05-21', 'active');

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
(39, 0, 0, 4, 7, 90, 1, 1, '0000-00-00', '2026-05-21 07:32:23', '2026-05-21 07:32:23');

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
-- Table structure for table `leave_types`
--

CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `max_days` int(11) NOT NULL DEFAULT 1,
  `direction` enum('past','future','any') NOT NULL DEFAULT 'any',
  `description_label` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leave_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leave_types` (`name`, `label`, `max_days`, `direction`, `description_label`, `sort_order`) VALUES
('sick leave',        'Sick Leave',        4,   'past',   'up to 4 past dates only (before today)', 1),
('vacation leave',    'Vacation Leave',    999, 'future', 'future dates only',                      2),
('birthday leave',    'Birthday Leave',    1,   'any',    '1 day only',                              3),
('solo parent leave', 'Solo Parent Leave', 2,   'any',    'up to 2 days',                            4);

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
  `log_type` enum('IN','OUT','BREAK_IN','BREAK_OUT','ADD_EMPLOYEE','EDIT_EMPLOYEE','DELETE_EMPLOYEE','ADD_SCHEDULE','EDIT_SCHEDULE') DEFAULT NULL,
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
(828, 22, NULL, 'IN', '2026-05-12 07:21:00', 120.9955124, 14.5842482, 55, 1, 22.2953, 'cap_22_20260512_162137.jpg', '2026-05-12 08:21:37', NULL, NULL, NULL, NULL, NULL),
(829, 22, NULL, 'BREAK_IN', '2026-05-12 12:00:00', 120.9954947, 14.5842564, 55, 1, 22.68, NULL, '2026-05-12 08:21:49', NULL, NULL, NULL, NULL, NULL),
(830, 22, NULL, 'BREAK_OUT', '2026-05-12 13:07:00', 120.9954947, 14.5842564, 55, 1, 22.68, NULL, '2026-05-12 08:21:57', NULL, NULL, NULL, NULL, NULL),
(831, 22, NULL, 'OUT', '2026-05-12 18:22:00', 120.9954947, 14.5842564, 55, 1, 22.6814, 'cap_22_20260512_162205.jpg', '2026-05-12 08:22:05', NULL, NULL, NULL, NULL, NULL),
(832, 3, NULL, 'IN', '2026-05-14 17:28:58', 120.9777000, 14.4470000, 20000, 0, 15401.3, 'cap_3_20260514_172858.jpg', '2026-05-14 09:28:58', NULL, NULL, NULL, NULL, NULL),
(833, 3, NULL, 'BREAK_IN', '2026-05-14 17:29:11', 120.9777000, 14.4470000, 20000, 0, 15401.3, NULL, '2026-05-14 09:29:11', NULL, NULL, NULL, NULL, NULL),
(834, 3, NULL, 'BREAK_OUT', '2026-05-14 17:29:19', 120.9777000, 14.4470000, 20000, 0, 15401.3, NULL, '2026-05-14 09:29:19', NULL, NULL, NULL, NULL, NULL),
(835, 3, NULL, 'OUT', '2026-05-14 17:29:27', 120.9777000, 14.4470000, 20000, 0, 15401.3, 'cap_3_20260514_172927.jpg', '2026-05-14 09:29:27', NULL, NULL, NULL, NULL, NULL),
(839, 22, NULL, 'IN', '2026-05-19 10:21:59', 120.9954955, 14.5842598, 55, 1, 22.3334, 'cap_22_20260519_102159.jpg', '2026-05-19 02:21:59', NULL, NULL, NULL, NULL, NULL),
(840, 22, NULL, 'OUT', '2026-05-19 20:00:00', 120.9954906, 14.5842627, 55, 1, 22.4192, 'cap_22_20260519_135733.jpg', '2026-05-19 05:57:33', NULL, NULL, NULL, NULL, NULL),
(841, 22, NULL, 'IN', '2026-05-20 09:21:06', 120.9955099, 14.5842550, 55, 1, 21.8204, 'cap_22_20260520_092106.jpg', '2026-05-20 01:21:06', NULL, NULL, NULL, NULL, NULL),
(842, 22, NULL, 'OUT', '2026-05-20 17:30:00', 120.9955066, 14.5842572, 55, 1, 21.8326, 'cap_22_20260520_092128.jpg', '2026-05-20 01:21:28', NULL, NULL, NULL, NULL, NULL),
(847, 22, NULL, 'IN', '2026-05-21 09:00:00', 120.9955054, 14.5842591, 55, 1, 21.7368, 'cap_22_20260521_093501.jpg', '2026-05-21 01:35:01', NULL, NULL, NULL, NULL, NULL),
(848, 22, NULL, 'OUT', '2026-05-21 20:00:00', 120.9955005, 14.5842534, 55, 1, 22.5565, 'cap_22_20260521_094019.jpg', '2026-05-21 01:40:19', NULL, NULL, NULL, NULL, NULL),
(849, 20, NULL, 'IN', '2026-05-20 13:50:08', 120.9955088, 14.5842496, 55, 1, 22.3849, 'cap_20_20260520_135008.jpg', '2026-05-20 05:50:08', NULL, NULL, NULL, NULL, NULL),
(850, 20, NULL, 'BREAK_IN', '2026-05-20 13:50:13', 120.9955088, 14.5842496, 55, 1, 22.38, NULL, '2026-05-20 05:50:13', NULL, NULL, NULL, NULL, NULL),
(851, 20, NULL, 'BREAK_OUT', '2026-05-20 13:50:22', 120.9955088, 14.5842496, 55, 1, 22.38, NULL, '2026-05-20 05:50:22', NULL, NULL, NULL, NULL, NULL),
(852, 20, NULL, 'OUT', '2026-05-20 13:50:27', 120.9955088, 14.5842496, 55, 1, 22.3849, 'cap_20_20260520_135027.jpg', '2026-05-20 05:50:27', NULL, NULL, NULL, NULL, NULL),
(857, 20, NULL, 'IN', '2026-05-22 08:30:00', 120.9954791, 14.5842707, 55, 1, 22.5997, 'cap_20_20260522_111215.jpg', '2026-05-22 03:12:15', NULL, NULL, NULL, NULL, NULL),
(858, 20, NULL, 'OUT', '2026-05-22 11:28:41', 120.9955018, 14.5842564, 55, 1, 22.2095, 'cap_20_20260522_112841.jpg', '2026-05-22 03:28:41', NULL, NULL, NULL, NULL, NULL),
(861, 19, NULL, 'IN', '2026-05-22 15:12:15', 120.9954925, 14.5842611, 55, 1, 22.4298, 'cap_19_20260522_151215.jpg', '2026-05-22 07:12:15', NULL, NULL, NULL, NULL, NULL),
(862, 22, NULL, 'IN', '2026-05-22 08:00:00', 120.9954891, 14.5842634, 55, 1, 22.4659, 'cap_22_20260522_154256.jpg', '2026-05-22 07:42:56', NULL, NULL, NULL, NULL, NULL),
(865, 22, NULL, 'OUT', '2026-05-22 18:00:00', 120.9955029, 14.5842555, 55, 1, 22.2247, 'cap_22_20260522_160011.jpg', '2026-05-22 08:00:11', NULL, NULL, NULL, NULL, NULL),
(891, 19, NULL, 'IN', '2026-05-25 08:30:00', 120.9954976, 14.5842557, 55, 1, 22.5421, 'cap_19_20260525_095149.jpg', '2026-05-25 01:51:49', '2026-05-25 09:51:49', '2026-05-25 08:30:00', 'approved', 'No reason provided', 39),
(892, 39, NULL, 'IN', '2026-05-25 07:23:00', 120.9954913, 14.5842607, 55, 1, 22.5357, NULL, '2026-05-25 02:23:29', '2026-05-25 10:23:29', '2026-05-25 07:23:00', 'approved', 'No reason provided', 39);

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
(23, 22, 283, 840, '2026-05-19', 'time_out', NULL, '2026-05-19 20:00:00', 'No reason provided', 3, 'approved', '2026-05-19 07:11:47', '2026-05-19 07:11:47'),
(24, 22, 291, 848, '2026-05-21', 'time_out', NULL, '2026-05-21 20:00:00', 'No reason provided', 3, 'approved', '2026-05-20 06:10:53', '2026-05-20 06:10:53'),
(25, 22, 293, 842, '2026-05-20', 'time_out', NULL, '2026-05-20 17:30:00', 'Hallo', 39, 'approved', '2026-05-21 08:07:33', '2026-05-21 08:07:33'),
(26, 22, 291, 847, '2026-05-21', 'time_in', '2026-05-21 09:00:00', NULL, 'hllo', 22, 'approved', '2026-05-22 01:59:51', '2026-05-22 02:00:08'),
(32, 20, 331, 857, '2026-05-22', 'time_in', '2026-05-22 08:30:00', NULL, 'No reason provided', 20, 'approved', '2026-05-22 03:12:27', '2026-05-22 03:28:10'),
(33, 22, 367, 862, '2026-05-22', 'time_in', '2026-05-22 08:00:00', NULL, 'No reason provided', 22, 'approved', '2026-05-22 07:43:36', '2026-05-22 07:43:53'),
(36, 22, 367, 865, '2026-05-22', 'time_out', NULL, '2026-05-22 18:00:00', 'No reason provided', 22, 'approved', '2026-05-22 08:00:26', '2026-05-22 08:00:41');

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
(479, 22, NULL, '2026-05-22', '2026-05-22 08:30:00', '2026-05-22 17:30:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(490, 20, 19, '2026-05-25', '2026-05-25 08:30:00', '2026-05-25 17:30:00', 0, 'approved', 0, '2026-05-25 06:00:18', 0, 'added', '29b0447729a327af', NULL, NULL, NULL),
(491, 20, 19, '2026-05-26', '2026-05-26 08:30:00', '2026-05-26 17:30:00', 0, 'approved', 0, '2026-05-25 06:00:18', 0, 'added', '29b0447729a327af', NULL, NULL, NULL),
(492, 20, 19, '2026-05-27', '2026-05-27 08:30:00', '2026-05-27 17:30:00', 0, 'approved', 0, '2026-05-25 06:00:18', 0, 'added', '29b0447729a327af', NULL, NULL, NULL),
(493, 20, 19, '2026-05-28', '2026-05-28 08:30:00', '2026-05-28 17:30:00', 0, 'approved', 0, '2026-05-25 06:00:18', 0, 'added', '29b0447729a327af', NULL, NULL, NULL),
(494, 20, 19, '2026-05-29', '2026-05-29 08:30:00', '2026-05-29 17:30:00', 0, 'approved', 0, '2026-05-25 06:00:18', 0, 'added', '29b0447729a327af', NULL, NULL, NULL),
(495, 20, 19, '2026-05-30', '2026-05-30 08:30:00', '2026-05-30 17:30:00', 1, 'approved', 0, '2026-05-25 06:00:18', 0, 'added', '29b0447729a327af', NULL, NULL, NULL),
(502, 20, 19, '2026-05-23', '2026-05-23 19:30:00', '2026-05-24 04:30:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(510, 20, 19, '2026-05-10', '2026-05-10 08:30:00', '2026-05-10 17:30:00', 1, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(511, 20, 19, '2026-05-11', '2026-05-11 08:30:00', '2026-05-11 17:30:00', 0, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(512, 20, 19, '2026-05-12', '2026-05-12 08:30:00', '2026-05-12 17:30:00', 0, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(513, 20, 19, '2026-05-13', '2026-05-13 08:30:00', '2026-05-13 17:30:00', 0, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(514, 20, 19, '2026-05-14', '2026-05-14 08:30:00', '2026-05-14 17:30:00', 0, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(515, 20, 19, '2026-05-15', '2026-05-15 08:30:00', '2026-05-15 17:30:00', 0, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(516, 20, 19, '2026-05-16', '2026-05-16 08:30:00', '2026-05-16 17:30:00', 1, 'rejected', 0, '2026-05-25 06:12:13', 0, 'added', 'ea10a93b0bfd7a3a', NULL, NULL, NULL),
(517, 20, 19, '2026-05-17', '2026-05-17 19:30:00', '2026-05-18 04:30:00', 1, 'approved', 0, '2026-05-25 06:22:18', 0, 'added', 'b19bfcdff81f39ef', NULL, NULL, NULL),
(518, 20, 19, '2026-05-18', '2026-05-18 19:30:00', '2026-05-19 04:30:00', 0, 'approved', 0, '2026-05-25 06:22:18', 0, 'added', 'b19bfcdff81f39ef', NULL, NULL, NULL),
(519, 20, 19, '2026-05-19', '2026-05-19 19:30:00', '2026-05-20 04:30:00', 0, 'approved', 0, '2026-05-25 06:22:18', 0, 'added', 'b19bfcdff81f39ef', NULL, NULL, NULL),
(520, 20, 19, '2026-05-20', '2026-05-20 19:30:00', '2026-05-21 04:30:00', 0, 'approved', 0, '2026-05-25 06:22:18', 0, 'added', 'b19bfcdff81f39ef', NULL, NULL, NULL),
(521, 20, 19, '2026-05-21', '2026-05-21 19:30:00', '2026-05-22 04:30:00', 0, 'approved', 0, '2026-05-25 06:22:18', 0, 'added', 'b19bfcdff81f39ef', NULL, NULL, NULL),
(522, 20, 19, '2026-05-22', '2026-05-22 19:30:00', '2026-05-23 04:30:00', 0, 'approved', 0, '2026-05-25 06:22:18', 0, 'added', 'b19bfcdff81f39ef', NULL, NULL, NULL),
(523, 20, 19, '2026-05-23', '2026-05-23 19:30:00', '2026-05-24 04:30:00', 0, 'approved', 0, '2026-05-25 06:23:33', 0, 'edit', '269a7774c5b264ae', 0, '2026-05-23 07:30:00', '2026-05-23 16:30:00'),
(524, 20, 19, '2026-05-24', '2026-05-24 19:30:00', '2026-05-25 04:30:00', 1, 'approved', 0, '2026-05-25 06:24:19', 0, NULL, NULL, NULL, NULL, NULL),
(532, 20, 15, '2026-05-01', '2026-05-01 09:00:00', '2026-05-01 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(533, 20, 15, '2026-05-02', '2026-05-02 09:00:00', '2026-05-02 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(534, 20, 15, '2026-05-03', '2026-05-03 09:00:00', '2026-05-03 18:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(535, 20, 15, '2026-05-04', '2026-05-04 09:00:00', '2026-05-04 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(536, 20, 15, '2026-05-05', '2026-05-05 09:00:00', '2026-05-05 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(537, 20, 15, '2026-05-06', '2026-05-06 09:00:00', '2026-05-06 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(538, 20, 15, '2026-05-07', '2026-05-07 09:00:00', '2026-05-07 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(539, 20, 15, '2026-05-08', '2026-05-08 09:00:00', '2026-05-08 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(540, 20, 15, '2026-05-09', '2026-05-09 09:00:00', '2026-05-09 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(541, 20, 15, '2026-05-10', '2026-05-10 09:00:00', '2026-05-10 18:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(542, 20, 15, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(543, 20, 15, '2026-05-12', '2026-05-12 09:00:00', '2026-05-12 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(544, 20, 15, '2026-05-13', '2026-05-13 09:00:00', '2026-05-13 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(545, 20, 15, '2026-05-14', '2026-05-14 09:00:00', '2026-05-14 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(546, 20, 15, '2026-05-15', '2026-05-15 09:00:00', '2026-05-15 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(547, 20, 15, '2026-05-16', '2026-05-16 09:00:00', '2026-05-16 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(548, 20, 15, '2026-05-17', '2026-05-17 09:00:00', '2026-05-17 18:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(549, 20, 15, '2026-05-18', '2026-05-18 09:00:00', '2026-05-18 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(550, 20, 15, '2026-05-19', '2026-05-19 09:00:00', '2026-05-19 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(551, 20, 15, '2026-05-20', '2026-05-20 09:00:00', '2026-05-20 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(552, 20, 15, '2026-05-21', '2026-05-21 09:00:00', '2026-05-21 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(553, 20, 15, '2026-05-22', '2026-05-22 09:00:00', '2026-05-22 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(554, 20, 15, '2026-05-23', '2026-05-23 09:00:00', '2026-05-23 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(555, 20, 15, '2026-05-24', '2026-05-24 09:00:00', '2026-05-24 18:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(556, 20, 15, '2026-05-25', '2026-05-25 09:00:00', '2026-05-25 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(557, 20, 15, '2026-05-26', '2026-05-26 09:00:00', '2026-05-26 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(558, 20, 15, '2026-05-27', '2026-05-27 09:00:00', '2026-05-27 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(559, 20, 15, '2026-05-28', '2026-05-28 09:00:00', '2026-05-28 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(560, 20, 15, '2026-05-29', '2026-05-29 09:00:00', '2026-05-29 18:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(561, 20, 15, '2026-05-30', NULL, NULL, 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(562, 20, 15, '2026-05-31', '2026-05-31 09:00:00', '2026-05-31 18:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(563, 22, 15, '2026-05-18', '2026-05-18 07:00:00', '2026-05-18 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(564, 22, 15, '2026-05-19', '2026-05-19 07:00:00', '2026-05-19 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(565, 22, 15, '2026-05-20', '2026-05-20 07:00:00', '2026-05-20 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(566, 22, 15, '2026-05-21', '2026-05-21 07:00:00', '2026-05-21 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(567, 22, 15, '2026-05-22', '2026-05-22 07:00:00', '2026-05-22 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(568, 22, 15, '2026-05-23', '2026-05-23 07:00:00', '2026-05-23 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(569, 22, 15, '2026-05-24', '2026-05-24 07:00:00', '2026-05-24 16:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(570, 22, 15, '2026-05-25', '2026-05-25 07:00:00', '2026-05-25 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(571, 22, 15, '2026-05-26', '2026-05-26 07:00:00', '2026-05-26 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(572, 22, 15, '2026-05-27', '2026-05-27 07:00:00', '2026-05-27 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(573, 22, 15, '2026-05-28', '2026-05-28 07:00:00', '2026-05-28 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(574, 22, 15, '2026-05-29', '2026-05-29 07:00:00', '2026-05-29 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(575, 22, 15, '2026-05-30', '2026-05-30 07:00:00', '2026-05-30 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(576, 22, 15, '2026-05-31', '2026-05-31 07:00:00', '2026-05-31 16:00:00', 1, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(577, 39, 39, '2026-05-27', '2026-05-27 07:00:00', '2026-05-27 16:00:00', 0, 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=893;

--
-- AUTO_INCREMENT for table `log_edit_requests`
--
ALTER TABLE `log_edit_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=578;

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
