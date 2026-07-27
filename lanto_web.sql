-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2026 at 09:49 AM
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
-- Database: `lanto_web`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ID ของพนักงาน',
  `log_type` enum('check_in','check_out') NOT NULL COMMENT 'ประเภทการสแกนเข้าหรือออก',
  `branch_id` int(11) DEFAULT NULL,
  `scan_time` datetime NOT NULL COMMENT 'เวลาสแกนจริง',
  `image_url` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'พิกัดละติจูด',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'พิกัดลองจิจูด',
  `photo_log` varchar(255) DEFAULT NULL COMMENT 'รูปถ่ายหลักฐานตอนสแกนหน้าเข้างาน',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `user_id`, `log_type`, `branch_id`, `scan_time`, `image_url`, `latitude`, `longitude`, `photo_log`, `created_at`) VALUES
(1, 2, 'check_in', NULL, '2026-07-10 17:31:59', NULL, 13.67760000, 100.72450000, 'face_69008_1783679519.jpg', '2026-07-10 10:31:59'),
(2, 2, 'check_in', NULL, '2026-07-13 08:57:25', NULL, 13.60590000, 100.70610000, 'face_69008_1783907845.jpg', '2026-07-13 01:57:25'),
(3, 2, 'check_out', NULL, '2026-07-13 08:57:34', NULL, 13.60590000, 100.70610000, 'face_69008_1783907854.jpg', '2026-07-13 01:57:34'),
(4, 2, 'check_in', NULL, '2026-07-14 10:29:26', NULL, 13.65400000, 100.72400000, 'face_69008_1783999766.jpg', '2026-07-14 03:29:26'),
(5, 2, 'check_out', NULL, '2026-07-14 11:17:01', NULL, 13.65400000, 100.72400000, 'face_69008_1784002621.jpg', '2026-07-14 04:17:01'),
(6, 2, 'check_in', NULL, '2026-07-14 11:17:21', NULL, 13.65400000, 100.72400000, 'face_69008_1784002641.jpg', '2026-07-14 04:17:21'),
(7, 2, 'check_out', NULL, '2026-07-14 11:19:09', NULL, 13.65400000, 100.72400000, 'face_69008_1784002749.jpg', '2026-07-14 04:19:09'),
(8, 2, 'check_in', 1, '2026-07-14 11:19:21', NULL, 13.65400000, 100.72400000, 'face_69008_1784002761.jpg', '2026-07-14 04:19:21'),
(9, 2, 'check_out', NULL, '2026-07-14 14:08:53', NULL, 13.65400000, 100.72400000, 'face_69008_1784012933.jpg', '2026-07-14 07:08:53'),
(10, 2, 'check_in', 1, '2026-07-14 14:09:32', NULL, 13.65400000, 100.72400000, 'face_69008_1784012972.jpg', '2026-07-14 07:09:32'),
(11, 1, 'check_in', 1, '2026-07-16 16:52:46', NULL, 13.65400000, 100.72400000, 'face_admin_1784195566.jpg', '2026-07-16 09:52:46'),
(12, 1, 'check_out', NULL, '2026-07-16 16:53:14', NULL, 13.65400000, 100.72400000, 'face_admin_1784195594.jpg', '2026-07-16 09:53:14'),
(13, 2, 'check_in', 1, '2026-07-16 17:12:35', NULL, 13.65400000, 100.72400000, 'face_69008_1784196755.jpg', '2026-07-16 10:12:35'),
(14, 2, 'check_out', NULL, '2026-07-16 17:12:45', NULL, 13.65400000, 100.72400000, 'face_69008_1784196765.jpg', '2026-07-16 10:12:45'),
(15, 1, 'check_in', 1, '2026-07-16 17:15:08', NULL, 13.65400000, 100.72400000, 'face_admin_1784196908.jpg', '2026-07-16 10:15:08'),
(16, 1, 'check_out', NULL, '2026-07-16 17:15:12', NULL, 13.65400000, 100.72400000, 'face_admin_1784196912.jpg', '2026-07-16 10:15:12'),
(17, 1, 'check_in', 3, '2026-07-16 17:15:19', NULL, 13.65400000, 100.72400000, 'face_admin_1784196919.jpg', '2026-07-16 10:15:19'),
(18, 1, 'check_out', NULL, '2026-07-16 17:15:24', NULL, 13.65400000, 100.72400000, 'face_admin_1784196924.jpg', '2026-07-16 10:15:24'),
(19, 1, 'check_in', 4, '2026-07-16 17:15:30', NULL, 13.65400000, 100.72400000, 'face_admin_1784196930.jpg', '2026-07-16 10:15:30'),
(20, 1, 'check_out', NULL, '2026-07-16 17:15:36', NULL, 13.65400000, 100.72400000, 'face_admin_1784196936.jpg', '2026-07-16 10:15:36'),
(21, 1, 'check_in', 1, '2026-07-20 08:50:46', NULL, 13.63000000, 100.75000000, 'face_admin_1784512246.jpg', '2026-07-20 01:50:46'),
(22, 1, 'check_out', NULL, '2026-07-20 11:40:52', NULL, 13.63000000, 100.75000000, 'face_admin_1784522452.jpg', '2026-07-20 04:40:52'),
(23, 1, 'check_in', 1, '2026-07-20 11:49:25', NULL, 13.63000000, 100.75000000, 'face_admin_1784522965.jpg', '2026-07-20 04:49:25'),
(24, 2, 'check_in', 1, '2026-07-22 16:08:06', NULL, 13.63000000, 100.75000000, 'face_69008_1784711286.jpg', '2026-07-22 09:08:06'),
(25, 2, 'check_out', NULL, '2026-07-22 16:08:16', NULL, 13.63000000, 100.75000000, 'face_69008_1784711296.jpg', '2026-07-22 09:08:16'),
(26, 2, 'check_in', 1, '2026-07-22 16:10:48', NULL, 13.63000000, 100.75000000, 'face_69008_1784711448.jpg', '2026-07-22 09:10:48'),
(27, 2, 'check_out', NULL, '2026-07-22 16:11:00', NULL, 13.63000000, 100.75000000, 'face_69008_1784711460.jpg', '2026-07-22 09:11:00'),
(28, 1, 'check_in', 1, '2026-07-23 16:38:21', NULL, 13.63000000, 100.75000000, 'face_admin_1784799501.jpg', '2026-07-23 09:38:21'),
(29, 2, 'check_in', 1, '2026-07-23 16:38:50', NULL, 13.63000000, 100.75000000, 'face_69008_1784799530.jpg', '2026-07-23 09:38:50'),
(30, 2, 'check_out', NULL, '2026-07-23 16:39:40', NULL, 13.63000000, 100.75000000, 'face_69008_1784799580.jpg', '2026-07-23 09:39:40');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'ชื่อที่ตั้ง/สาขา',
  `latitude` decimal(11,8) DEFAULT NULL COMMENT 'ละติจูด',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'ลองติจูด',
  `radius` int(11) DEFAULT 100 COMMENT 'รัศมีวงกลม หน่วยเมตร',
  `Maps_link` text DEFAULT NULL COMMENT 'ลิงก์ Google Maps',
  `allow_checkin_outside` tinyint(1) DEFAULT 0 COMMENT 'อนุญาตให้เข้างานนอกสถานที่',
  `allow_checkout_outside` tinyint(1) DEFAULT 0 COMMENT 'อนุญาตให้ออกงานนอกสถานที่',
  `see_only_branch` tinyint(1) DEFAULT 1 COMMENT '1=เห็นเฉพาะคนในสาขาตัวเอง, 0=เห็นทั้งหมด',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'สถานะการใช้งาน (1=เปิด, 0=ปิด)',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'สาขาเริ่มต้นสำหรับพนักงานใหม่',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `latitude`, `longitude`, `radius`, `Maps_link`, `allow_checkin_outside`, `allow_checkout_outside`, `see_only_branch`, `is_active`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'สำนักงานใหญ่', 13.67793206, 100.72455235, 100, 'https://maps.google.com/?q=13.67793206,100.72455235', 0, 0, 1, 1, 1, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(2, 'สำนักงานใหญ่ (เฉพาะหัวหน้า)', 13.67793206, 100.72455235, 100, 'https://maps.google.com/?q=13.67793206,100.72455235', 0, 0, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(3, 'คลังสินค้าสนามบินภูเก็ต', 8.10878800, 98.30797700, 100, 'https://maps.google.com/?q=8.10878800,98.30797700', 0, 0, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(4, 'Work From Home', NULL, NULL, 0, NULL, 1, 1, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(5, 'อบรมนอกสถานที่', NULL, NULL, 0, NULL, 1, 1, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(6, 'สำนักงานแหลมฉบัง', 13.07918300, 100.90710100, 100, 'https://maps.google.com/?q=13.07918300,100.90710100', 0, 0, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(7, 'สำนักงานพระราม 9', 13.75692100, 100.56570400, 100, 'https://maps.google.com/?q=13.75692100,100.56570400', 0, 0, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52'),
(8, 'คลังสินค้าสุวรรณภูมิ', 13.70657500, 100.75098500, 50, 'https://maps.google.com/?q=13.70657500,100.75098500', 0, 0, 1, 1, 0, '2026-07-08 10:32:52', '2026-07-08 10:32:52');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'ชื่อแผนกแบบเต็มและวงเล็บย่อ',
  `head_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'สถานะ (1=เปิดใช้งาน, 0=ปิด)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `head_user_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Executive Management (MGMT) - ฝ่ายบริหารจัดการองค์กร', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(2, 'Human Resources (HR) - ฝ่ายทรัพยากรบุคคล', 3, 1, '2026-07-09 09:25:22', '2026-07-24 04:36:11'),
(3, 'Finance & Accounting (FIN & ACC) - ฝ่ายการเงินและบัญชี', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(4, 'Information Technology Support (IT Support) - ฝ่ายสนับสนุนเทคโนโลยีสารสนเทศ', 4, 1, '2026-07-09 09:25:22', '2026-07-24 04:39:22'),
(5, 'Sales & Marketing (Sales & MKT) - ฝ่ายขายและการตลาด', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(6, 'Commercial & Pricing (Commercial) - ฝ่ายพาณิชย์และการจัดซื้อระวาง', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(7, 'Customer Service Airfreight (CS Air) - ฝ่ายบริการลูกค้าทางอากาศ', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(8, 'Customer Service Seafreight (CS Sea) - ฝ่ายบริการลูกค้าทางทะเล', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(9, 'Airfreight Operations (Airfreight) - ฝ่ายปฏิบัติการขนส่งสินค้าทางอากาศ', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(10, 'Cargo Consolidation (Console) - ฝ่ายจัดการและจัดตู้สินค้า', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(11, 'Customs Clearance Airfreight (Shipping Air) - ฝ่ายพิธีการศุลกากรทางอากาศ', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(12, 'Customs Clearance Seafreight (Shipping Sea) - ฝ่ายพิธีการศุลกากรทางทะเล', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(13, 'Warehouse Operations (Warehouse) - ฝ่ายบริหารและจัดการคลังสินค้า', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(14, 'Warehouse Logistics Driver (Driver WH) - ฝ่ายพนักงานขับรถคลังสินค้า', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(15, 'Domestic Transportation (Transport) - ฝ่ายจัดส่งและขนส่งสินค้าภายในประเทศ', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(16, 'Express Logistics (Express) - ฝ่ายบริการจัดส่งพัสดุด่วน', NULL, 1, '2026-07-09 09:25:22', '2026-07-09 09:25:22'),
(17, 'Sales & Marketing Rama 9 (Sales MKT-RM9) - ฝ่ายขายและการตลาด สาขาพระราม 9', NULL, 1, '2026-07-09 09:25:33', '2026-07-09 09:25:33'),
(18, 'Laem Chabang Branch Operations (LCB Ops) - ฝ่ายปฏิบัติการประจำสาขาแหลมฉบัง', NULL, 1, '2026-07-09 09:25:33', '2026-07-09 09:25:33');

-- --------------------------------------------------------

--
-- Table structure for table `employee_types`
--

DROP TABLE IF EXISTS `employee_types`;
CREATE TABLE `employee_types` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'ชื่อประเภทพนักงาน (ไทย-อังกฤษ)',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'สถานะการใช้งาน (1=เปิด, 0=ปิด)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_types`
--

INSERT INTO `employee_types` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Full-Time Employee (พนักงานประจำ)', 1, '2026-07-09 09:27:22', '2026-07-09 09:27:22'),
(2, 'Probationary Employee (พนักงานทดลองงาน)', 1, '2026-07-09 09:27:22', '2026-07-09 09:27:22'),
(3, 'Contract / Temporary Employee (พนักงานสัญญาจ้าง / ชั่วคราว)', 1, '2026-07-09 09:27:22', '2026-07-09 09:27:22'),
(4, 'Part-Time / Daily Employee (พนักงานรายวัน / พาร์ทไทม์)', 1, '2026-07-09 09:27:22', '2026-07-09 09:27:22'),
(5, 'Internship / Co-op Student (นักศึกษาฝึกงาน / สหกิจศึกษา)', 1, '2026-07-09 09:27:22', '2026-07-09 09:27:22'),
(6, 'Outsource Personnel (พนักงานภายนอก / เอาท์ซอร์ส)', 1, '2026-07-09 09:27:22', '2026-07-09 09:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `leave_duration` enum('full','half','hourly') NOT NULL DEFAULT 'full',
  `leave_hours` int(11) DEFAULT 0,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `attachment` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reject_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `user_id`, `leave_type`, `leave_duration`, `leave_hours`, `start_date`, `end_date`, `reason`, `attachment`, `status`, `reject_reason`, `created_at`) VALUES
(1, 2, 'other', 'full', 0, '2026-07-14', '2026-07-15', '555', 'LEAVE_2_1783999286.png', 'pending', NULL, '2026-07-14 03:21:26'),
(2, 2, 'other', 'hourly', 5, '2026-07-14', '2026-07-15', 'ฟฟ', 'LEAVE_2_1783999503.png', 'pending', NULL, '2026-07-14 03:25:03'),
(3, 2, 'other', 'full', 0, '2026-07-14', '2026-07-15', 'ฟก', 'LEAVE_2_1783999517.png', 'pending', NULL, '2026-07-14 03:25:17'),
(4, 2, 'other', 'full', 0, '2026-07-14', '2026-07-15', 'dad', 'LEAVE_2_1784021733.png', 'pending', NULL, '2026-07-14 09:35:33'),
(5, 1, 'sick', 'full', 0, '2026-07-15', '2026-07-15', 'ฟฟฟ', 'LEAVE_1_1784089381.png', 'pending', NULL, '2026-07-15 04:23:01'),
(6, 1, 'personal', 'full', 0, '2026-07-20', '2026-07-21', 'กฟกฟก', 'LEAVE_1_1784521515.png', 'rejected', 'ฟกฟก', '2026-07-20 04:25:15'),
(7, 1, 'personal', 'full', 0, '2026-07-20', '2026-07-21', 'ฟกฟก', 'LEAVE_1_1784528077.png', 'rejected', NULL, '2026-07-20 06:14:37'),
(8, 1, 'personal', 'full', 0, '2026-07-23', '2026-07-24', '-', 'LEAVE_1_1784778087.png', 'rejected', NULL, '2026-07-23 03:41:27'),
(9, 1, 'sick', 'full', 0, '2026-07-23', '2026-07-24', 'ฟก', 'LEAVE_1_1784787175.jpg', 'rejected', NULL, '2026-07-23 06:12:55'),
(10, 4, 'sick', 'full', 0, '2026-07-23', '2026-07-23', '456666', 'LEAVE_4_1784790301.png', 'approved', NULL, '2026-07-23 07:05:01');

-- --------------------------------------------------------

--
-- Table structure for table `salaries`
--

DROP TABLE IF EXISTS `salaries`;
CREATE TABLE `salaries` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `month` varchar(2) NOT NULL,
  `year` varchar(4) NOT NULL,
  `base_salary` decimal(10,2) DEFAULT 0.00,
  `ot_pay` decimal(10,2) DEFAULT 0.00,
  `bonus` decimal(10,2) DEFAULT 0.00,
  `social_fund` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) DEFAULT 0.00,
  `pdf_file` varchar(255) DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salaries`
--

INSERT INTO `salaries` (`id`, `employee_id`, `month`, `year`, `base_salary`, `ot_pay`, `bonus`, `social_fund`, `net_pay`, `pdf_file`, `is_published`, `created_at`) VALUES
(1, 2, '07', '2026', 25000.00, 0.00, 0.00, 750.00, 24250.00, NULL, 1, '2026-07-24 06:55:04'),
(2, 4, '07', '2026', 0.00, 0.00, 0.00, 0.00, 50000.00, 'payslip_4_202607_1784880518.pdf', 1, '2026-07-24 08:08:38');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'ID ผู้ดำเนินการ',
  `action` varchar(255) NOT NULL COMMENT 'หัวข้อการกระทำ',
  `details` text DEFAULT NULL COMMENT 'รายละเอียดเพิ่มเติม',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP Address ผู้ใช้งาน',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'วันและเวลาที่ทำรายการ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'แก้ไขแผนก', 'อัปเดตแผนก: Human Resources (HR) - ฝ่ายทรัพยากรบุคคล (ID: 2)', '::1', '2026-07-24 04:36:11'),
(2, 1, 'แก้ไขแผนก', 'อัปเดตแผนก: Information Technology Support (IT Support) - ฝ่ายสนับสนุนเทคโนโลยีสารสนเทศ (ID: 4)', '::1', '2026-07-24 04:36:33'),
(3, 1, 'แก้ไขแผนก', 'อัปเดตแผนก: Information Technology Support (IT Support) - ฝ่ายสนับสนุนเทคโนโลยีสารสนเทศ (ID: 4)', '::1', '2026-07-24 04:39:14'),
(4, 1, 'แก้ไขแผนก', 'อัปเดตแผนก: Information Technology Support (IT Support) - ฝ่ายสนับสนุนเทคโนโลยีสารสนเทศ (ID: 4)', '::1', '2026-07-24 04:39:22'),
(5, 1, 'แก้ไขโควตาวันลา', 'อัปเดตเงื่อนไขวันลาประจำปี', '::1', '2026-07-24 08:43:19'),
(6, 1, 'แก้ไขแผนก', 'อัปเดตแผนก: Express Logistics (Express) - ฝ่ายบริการจัดส่งพัสดุด่วน (ID: 16)', '::1', '2026-07-24 09:25:30'),
(7, 1, 'เพิ่มกะงานใหม่', 'สร้างกะงาน: กะเช้า(Driver)', '::1', '2026-07-27 05:02:44');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('business_quota', '6', '2026-07-24 04:24:15'),
('company_address', '123/45 สำนักงานใหญ่ กรุงเทพมหานคร 10900', '2026-07-24 04:24:15'),
('company_email', 'hr@lantoweb.com', '2026-07-24 04:24:15'),
('company_name', 'บริษัท แลนโต เทคโนโลยี จำกัด', '2026-07-24 04:24:15'),
('company_phone', '02-123-4567', '2026-07-24 04:24:15'),
('company_tax_id', '0105550000000', '2026-07-24 04:24:15'),
('email_leave_notify', '1', '2026-07-24 04:24:15'),
('email_payslip_notify', '1', '2026-07-24 04:24:15'),
('line_notify_enabled', '1', '2026-07-24 04:24:15'),
('line_notify_token', '', '2026-07-24 04:24:15'),
('sick_cert_days', '3', '2026-07-24 08:43:19'),
('sick_quota', '30', '2026-07-24 04:24:15'),
('vacation_advance_days', '1', '2026-07-24 08:43:19'),
('vacation_quota', '6', '2026-07-24 04:24:15');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_code` varchar(50) NOT NULL COMMENT '3. รหัสพนักงาน (ใช้เป็น Username ในการ Login ได้ด้วย)',
  `password` varchar(255) NOT NULL COMMENT '4. รหัสผ่าน (เข้ารหัส bcrypt)',
  `role` enum('admin','employee','it_support','hr') NOT NULL DEFAULT 'employee' COMMENT 'สิทธิ์การใช้งานระบบ',
  `profile_image` varchar(255) DEFAULT NULL COMMENT '1. พาธเก็บชื่อไฟล์รูปถ่ายตัวเอง',
  `id_card_image` varchar(255) DEFAULT NULL COMMENT '2. พาธเก็บชื่อไฟล์รูปบัตรประชาชน',
  `fullname` varchar(100) NOT NULL COMMENT '6. ชื่อ-นามสกุล',
  `birth_date` date NOT NULL COMMENT '7. วัน/เดือน/ปี เกิด',
  `email` varchar(100) NOT NULL COMMENT '8. อีเมลพนักงาน',
  `phone` varchar(20) DEFAULT NULL,
  `address_detail` text NOT NULL COMMENT '9.1 บ้านเลขที่/หมู่บ้าน/ถนน',
  `subdistrict` varchar(100) NOT NULL COMMENT '9.2 ตำบล/แขวง',
  `district` varchar(100) NOT NULL COMMENT '9.3 อำเภอ/เขต',
  `province` varchar(100) NOT NULL COMMENT '9.4 จังหวัด',
  `zipcode` varchar(10) NOT NULL COMMENT '9.5 รหัสไปรษณีย์',
  `branch_id` int(11) DEFAULT NULL,
  `employee_type` int(11) DEFAULT NULL COMMENT 'เชื่อมโยงตาราง employee_types',
  `department` int(11) DEFAULT NULL COMMENT 'เชื่อมโยงตาราง departments',
  `start_date` date NOT NULL COMMENT '12. วันเริ่มทำงาน',
  `work_shift` int(11) DEFAULT NULL COMMENT 'เชื่อมโยงตาราง work_shifts',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'สถานะใช้งาน (1=เปิด, 0=ปิด)',
  `face_descriptor` longtext DEFAULT NULL COMMENT 'ข้อมูล Vector ใบหน้า 128 ค่า จาก face-api.js',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `employee_code`, `password`, `role`, `profile_image`, `id_card_image`, `fullname`, `birth_date`, `email`, `phone`, `address_detail`, `subdistrict`, `district`, `province`, `zipcode`, `branch_id`, `employee_type`, `department`, `start_date`, `work_shift`, `is_active`, `face_descriptor`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, 'ผู้ดูแลระบบ Lanto Web', '1990-01-01', 'admin@lantoweb.com', '0996506613', 'บ้านเลขที่ 4 | หมู่บ้าน/อาคาร 4 | ซอย 4 | ถนน 4', 'จอมพล', 'จตุจักร', 'กรุงเทพมหานคร', '10900', 0, 0, 0, '2026-01-01', 0, 1, NULL, '2026-07-07 08:19:07'),
(2, '69008', '$2y$10$fc84Cvx79XbbPNX0Ddm52OEPyl1VRV3Mdd8NAApSQbEFCIuNWrD2q', 'employee', 'profile_69008_1783418078.jpg', 'idcard_69008_1783418078.jpg', 'มงคล คำสุ', '0000-00-00', 'mongkolkamsu@gmail.com', '0996506613', 'บ้านเลขที่ 4 | หมู่บ้าน/อาคาร 6 | ซอย 46/2 | ถนน กิ่งแก้ว', 'ราชาเทวะ', 'บางพลี', 'สมุทรปราการ', '10540', 1, 1, 10, '0000-00-00', 1, 1, NULL, '2026-07-07 09:54:38'),
(3, 'TestHR', '$2y$10$S.xi.hPXLRnHj/6HEQipwOCaocn1786aainaGwrG./jHkjgNKv7ta', 'hr', 'profile_TestHR_1784021214.png', 'idcard_TestHR_1784021214.png', 'TestHR', '0000-00-00', 'TestHR@gmail.com', NULL, 'บ้านเลขที่ - | หมู่บ้าน/อาคาร - | ซอย - | ถนน -', 'พระบรมมหาราชวัง', '1001', '1', '10200', 1, 1, 2, '0000-00-00', 1, 1, NULL, '2026-07-14 09:26:54'),
(4, 'TestIT', '$2y$10$NeeI7v3xOXC/B2BHasEA.u2c4xropyT2U/6XWZdi.OHgbpBc4x9sq', 'it_support', 'profile_TestIT_1784021457.png', 'idcard_TestIT_1784021457.jpg', 'TestIT', '0000-00-00', 'TestIT@gmail.com', NULL, 'บ้านเลขที่ - | หมู่บ้าน/อาคาร - | ซอย - | ถนน -', 'พระบรมมหาราชวัง', '1001', '1', '10200', 1, 1, 4, '0000-00-00', 1, 1, NULL, '2026-07-14 09:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `work_shifts`
--

DROP TABLE IF EXISTS `work_shifts`;
CREATE TABLE `work_shifts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'ชื่อกะการทำงาน',
  `start_time` time NOT NULL COMMENT 'เวลาเริ่มเข้างาน',
  `end_time` time NOT NULL COMMENT 'เวลาเลิกงาน',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'สถานะการใช้งาน (1=เปิด, 0=ปิด)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_shifts`
--

INSERT INTO `work_shifts` (`id`, `name`, `start_time`, `end_time`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'กะปกติ (Normal Shift)', '08:30:00', '17:30:00', 1, '2026-07-09 09:28:45', '2026-07-09 09:28:45'),
(2, 'กะเช้า (Morning Shift)', '07:00:00', '16:00:00', 1, '2026-07-09 09:28:45', '2026-07-09 09:28:45'),
(3, 'กะดึก (Night Shift)', '22:00:00', '07:00:00', 1, '2026-07-09 09:28:45', '2026-07-09 09:28:45'),
(4, 'กะเช้า(Driver)', '08:30:00', '17:30:00', 1, '2026-07-27 05:02:44', '2026-07-27 05:02:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dept` (`name`);

--
-- Indexes for table `employee_types`
--
ALTER TABLE `employee_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_type` (`name`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `salaries`
--
ALTER TABLE `salaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `work_shifts`
--
ALTER TABLE `work_shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shift_name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `employee_types`
--
ALTER TABLE `employee_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `salaries`
--
ALTER TABLE `salaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `work_shifts`
--
ALTER TABLE `work_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `fk_system_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
