-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 12:52 PM
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
-- Database: `transport_bill`
--

-- --------------------------------------------------------

--
-- Table structure for table `branch`
--

CREATE TABLE `branch` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `branch_code` varchar(10) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` char(6) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branch`
--

INSERT INTO `branch` (`id`, `branch_name`, `branch_code`, `address`, `city`, `state`, `pincode`, `phone`, `email`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Bolpur', 'BOL', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-09-23 14:56:54', '2026-09-23 14:56:54'),
(2, 'Kolkata', 'KOL', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-09-23 14:57:06', '2026-09-23 14:57:06'),
(3, 'Jharkhand', 'JKD', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-09-23 14:57:28', '2026-09-23 14:57:41');

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

CREATE TABLE `driver` (
  `id` int(10) UNSIGNED NOT NULL,
  `driver_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `license_no` varchar(30) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` char(6) DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver`
--

INSERT INTO `driver` (`id`, `driver_name`, `phone`, `license_no`, `license_expiry`, `address`, `city`, `state`, `pincode`, `branch_id`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Ramesh Ghosh', '08101764369', 'WB006813655626', NULL, 'DEWAR, SAHORA, BURWAN, MURSHIDABAD, 731234', 'DEWAR', 'West Bengal', '731234', 1, 1, '2026-09-23 15:21:53', '2026-09-23 15:21:53'),
(2, 'Ricky Ghosh', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, 1, '2026-09-23 16:12:19', '2026-09-23 16:12:19');

-- --------------------------------------------------------

--
-- Table structure for table `expense`
--

CREATE TABLE `expense` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `category` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vendor_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense`
--

INSERT INTO `expense` (`id`, `trip_id`, `expense_date`, `category`, `amount`, `vendor_id`, `notes`, `branch_id`, `created_by`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-09-23', 'Parking', 50.00, NULL, 'Parking Fee', 1, 1, 1, '2026-09-23 10:18:46', '2026-09-23 10:18:46');

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE `item` (
  `id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(80) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lorry`
--

CREATE TABLE `lorry` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `lorry_number` varchar(15) NOT NULL,
  `lorry_type` varchar(20) DEFAULT NULL,
  `capacity` varchar(15) DEFAULT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` char(6) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lorry`
--

INSERT INTO `lorry` (`id`, `branch_id`, `lorry_number`, `lorry_type`, `capacity`, `owner_name`, `address`, `city`, `state`, `pincode`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'WB58BW8073', 'Open', '10 Ton', 'Ramesh Ghosh', NULL, NULL, NULL, NULL, 1, '2026-09-23 15:21:21', '2026-09-23 15:21:21'),
(2, 3, 'WB55SD8045', 'Tanker', '5 TON', 'Ricky Ghosh', NULL, NULL, NULL, NULL, 1, '2026-09-23 16:11:57', '2026-09-23 16:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `party`
--

CREATE TABLE `party` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `legal_name` varchar(255) NOT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `active` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `party`
--

INSERT INTO `party` (`id`, `branch_id`, `gstin`, `legal_name`, `trade_name`, `address`, `city`, `state`, `pincode`, `phone`, `email`, `status`, `created_at`, `updated_at`, `active`) VALUES
(1, 1, '19GNLPS2005D1ZZ', 'Rina Saha', 'Playbees Technologies', 'Ward 6, House 456, Ground Floor, Udayanpally, Bolpur', 'Bolpur', NULL, '731204', NULL, NULL, 'Active', '2026-09-23 15:43:59', '2026-09-23 15:43:59', 1),
(2, 2, '19AMFPT0703N1ZQ', 'Dipak Mishra', 'Dipak Transport', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-23 15:55:51', '2026-09-23 15:55:51', 1),
(3, 3, '19GNLPS2005D1ZU', 'Subhojit Ptamanick', 'JH Ciment', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-23 16:14:40', '2026-09-23 16:14:40', 1);

-- --------------------------------------------------------

--
-- Table structure for table `permission`
--

CREATE TABLE `permission` (
  `id` int(10) UNSIGNED NOT NULL,
  `perm_key` varchar(60) NOT NULL,
  `perm_name` varchar(100) NOT NULL,
  `perm_group` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permission`
--

INSERT INTO `permission` (`id`, `perm_key`, `perm_name`, `perm_group`, `sort_order`) VALUES
(1, 'branch.view', 'View Branches', 'Branch', 10),
(2, 'branch.create', 'Create Branch', 'Branch', 20),
(3, 'branch.edit', 'Edit Branch', 'Branch', 30),
(4, 'branch.delete', 'Delete Branch', 'Branch', 40),
(5, 'party.view', 'View Parties', 'Party', 10),
(6, 'party.create', 'Create Party', 'Party', 20),
(7, 'party.edit', 'Edit Party', 'Party', 30),
(8, 'party.delete', 'Delete Party', 'Party', 40),
(9, 'lorry.view', 'View Lorries', 'Lorry', 10),
(10, 'lorry.create', 'Create Lorry', 'Lorry', 20),
(11, 'lorry.edit', 'Edit Lorry', 'Lorry', 30),
(12, 'lorry.delete', 'Delete Lorry', 'Lorry', 40),
(13, 'driver.view', 'View Drivers', 'Driver', 10),
(14, 'driver.create', 'Create Driver', 'Driver', 20),
(15, 'driver.edit', 'Edit Driver', 'Driver', 30),
(16, 'driver.delete', 'Delete Driver', 'Driver', 40),
(17, 'trip.view', 'View Trips', 'Trip', 10),
(18, 'trip.create', 'Create Trip', 'Trip', 20),
(19, 'trip.edit', 'Edit Trip', 'Trip', 30),
(20, 'trip.delete', 'Delete Trip', 'Trip', 40),
(21, 'invoice.view', 'View Invoices', 'Invoice', 10),
(22, 'invoice.create', 'Create Invoice', 'Invoice', 20),
(23, 'invoice.edit', 'Edit Invoice', 'Invoice', 30),
(24, 'invoice.delete', 'Delete Invoice', 'Invoice', 40),
(25, 'payment.view', 'View Payments', 'Payment', 10),
(26, 'payment.create', 'Record Payment', 'Payment', 20),
(27, 'payment.delete', 'Delete Payment', 'Payment', 30),
(28, 'user.view', 'View Users', 'User', 10),
(29, 'user.create', 'Create User', 'User', 20),
(30, 'user.edit', 'Edit User', 'User', 30),
(31, 'user.delete', 'Delete User', 'User', 40),
(32, 'role.view', 'View Roles', 'Role', 10),
(33, 'role.create', 'Create Role', 'Role', 20),
(34, 'role.edit', 'Edit Role', 'Role', 30),
(35, 'role.delete', 'Delete Role', 'Role', 40),
(36, 'report.view', 'View Reports', 'Report', 10),
(37, 'setting.manage', 'Manage Settings', 'Setting', 10),
(42, 'vendor.view', 'View Vendors', 'Vendor', 10),
(43, 'vendor.create', 'Create Vendor', 'Vendor', 20),
(44, 'vendor.edit', 'Edit Vendor', 'Vendor', 30),
(45, 'vendor.delete', 'Delete Vendor', 'Vendor', 40),
(50, 'item.view', 'View Items', 'Item', 10),
(51, 'item.create', 'Create Item', 'Item', 20),
(52, 'item.edit', 'Edit Item', 'Item', 30),
(53, 'item.delete', 'Delete Item', 'Item', 40),
(54, 'trip.view.all', 'View All Trips (all branches)', 'Trip', 15);

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_desc` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`id`, `role_name`, `role_desc`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'Full access to everything', 1, '2026-09-21 13:37:10', '2026-09-21 13:37:10'),
(2, 'Branch Manager', 'Manages a single branch', 1, '2026-09-21 13:37:10', '2026-09-21 13:37:10'),
(3, 'Operator', 'Daily data entry — parties, trips', 1, '2026-09-21 13:37:10', '2026-09-21 13:37:10'),
(4, 'Accountant', 'Invoices, payments, and reports', 1, '2026-09-21 13:37:10', '2026-09-21 13:37:10'),
(5, ' Tester', 'Test Only', 1, '2026-09-22 11:28:20', '2026-09-22 11:50:43');

-- --------------------------------------------------------

--
-- Table structure for table `role_permission`
--

CREATE TABLE `role_permission` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permission`
--

INSERT INTO `role_permission` (`id`, `role_id`, `permission_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 4),
(5, 1, 5),
(6, 1, 6),
(7, 1, 7),
(8, 1, 8),
(9, 1, 9),
(10, 1, 10),
(11, 1, 11),
(12, 1, 12),
(13, 1, 13),
(14, 1, 14),
(15, 1, 15),
(16, 1, 16),
(17, 1, 17),
(18, 1, 18),
(19, 1, 19),
(20, 1, 20),
(21, 1, 21),
(22, 1, 22),
(23, 1, 23),
(24, 1, 24),
(25, 1, 25),
(26, 1, 26),
(27, 1, 27),
(28, 1, 28),
(29, 1, 29),
(30, 1, 30),
(31, 1, 31),
(32, 1, 32),
(33, 1, 33),
(34, 1, 34),
(35, 1, 35),
(36, 1, 36),
(37, 1, 37),
(95, 4, 5),
(96, 4, 17),
(97, 4, 21),
(98, 4, 22),
(99, 4, 23),
(100, 4, 25),
(101, 4, 26),
(102, 4, 36),
(151, 2, 1),
(152, 2, 2),
(153, 2, 3),
(154, 2, 4),
(155, 2, 13),
(156, 2, 14),
(157, 2, 15),
(158, 2, 16),
(159, 2, 21),
(160, 2, 22),
(161, 2, 23),
(162, 2, 24),
(163, 2, 9),
(164, 2, 10),
(165, 2, 11),
(166, 2, 12),
(167, 2, 5),
(168, 2, 6),
(169, 2, 7),
(170, 2, 8),
(171, 2, 25),
(172, 2, 26),
(173, 2, 27),
(174, 2, 36),
(175, 2, 37),
(176, 2, 17),
(177, 2, 18),
(178, 2, 19),
(179, 2, 20),
(314, 1, 42),
(315, 1, 43),
(316, 1, 44),
(317, 1, 45),
(328, 1, 50),
(329, 1, 51),
(330, 1, 52),
(331, 1, 53),
(335, 1, 54),
(336, 3, 13),
(337, 3, 14),
(338, 3, 15),
(339, 3, 21),
(340, 3, 50),
(341, 3, 9),
(342, 3, 10),
(343, 3, 11),
(344, 3, 5),
(345, 3, 6),
(346, 3, 7),
(347, 3, 25),
(348, 3, 17),
(349, 3, 18),
(350, 3, 19);

-- --------------------------------------------------------

--
-- Table structure for table `trip`
--

CREATE TABLE `trip` (
  `id` int(11) NOT NULL,
  `trip_no` varchar(30) NOT NULL,
  `lorry_id` int(11) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `from_branch_id` int(11) NOT NULL,
  `to_branch_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip`
--

INSERT INTO `trip` (`id`, `trip_no`, `lorry_id`, `driver_id`, `from_branch_id`, `to_branch_id`, `start_date`, `end_date`, `status`, `notes`, `branch_id`, `created_by`, `active`, `created_at`, `updated_at`) VALUES
(1, 'BOL-2609-0001', 1, 1, 1, 2, '2026-09-23', NULL, 'Scheduled', 'Transporting Food Bolpur to Kolkata', 1, 1, 1, '2026-09-23 10:17:08', '2026-09-23 10:17:08'),
(2, 'JKD-2609-0001', 2, 2, 3, 1, '2026-09-23', NULL, 'Scheduled', NULL, 3, 3, 1, '2026-09-23 10:49:50', '2026-09-23 10:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `trip_item`
--

CREATE TABLE `trip_item` (
  `id` int(11) NOT NULL,
  `trip_party_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT 0.000,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip_item`
--

INSERT INTO `trip_item` (`id`, `trip_party_id`, `item_id`, `item_name`, `unit`, `quantity`, `rate`, `amount`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Rice', '50 KG', 5.000, 1200.00, 6000.00, 1, '2026-09-23 10:17:08', '2026-09-23 10:17:08'),
(2, 2, NULL, 'Ciment', '50 KG', 50.000, 450.00, 22500.00, 1, '2026-09-23 10:49:50', '2026-09-23 10:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `trip_party`
--

CREATE TABLE `trip_party` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `party_id` int(11) NOT NULL,
  `freight_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip_party`
--

INSERT INTO `trip_party` (`id`, `trip_id`, `party_id`, `freight_amount`, `advance_paid`, `notes`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 5000.00, 0.00, NULL, 1, '2026-09-23 10:17:08', '2026-09-23 10:17:08'),
(2, 2, 3, 10000.00, 0.00, NULL, 1, '2026-09-23 10:49:50', '2026-09-23 10:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(30) NOT NULL,
  `password` varchar(30) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `password`, `full_name`, `branch_id`, `role_id`, `active`, `created_at`, `updated_at`) VALUES
(1, 'starramesh', '225588', '[STAR RAMESH]', 1, 1, 1, '2026-09-21 14:37:05', '2026-09-21 16:27:19'),
(3, 'ramesh', '123456', 'Ramesh Ghosh', 3, 3, 1, '2026-09-23 14:59:32', '2026-09-23 15:52:47');

-- --------------------------------------------------------

--
-- Table structure for table `vendor`
--

CREATE TABLE `vendor` (
  `id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `vendor_name` varchar(100) NOT NULL,
  `vendor_type` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `branch`
--
ALTER TABLE `branch`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver`
--
ALTER TABLE `driver`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense`
--
ALTER TABLE `expense`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lorry`
--
ALTER TABLE `lorry`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `party`
--
ALTER TABLE `party`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permission`
--
ALTER TABLE `permission`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permission`
--
ALTER TABLE `role_permission`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip`
--
ALTER TABLE `trip`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_item`
--
ALTER TABLE `trip_item`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trip_party`
--
ALTER TABLE `trip_party`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendor`
--
ALTER TABLE `vendor`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `branch`
--
ALTER TABLE `branch`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `driver`
--
ALTER TABLE `driver`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `expense`
--
ALTER TABLE `expense`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lorry`
--
ALTER TABLE `lorry`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `party`
--
ALTER TABLE `party`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `permission`
--
ALTER TABLE `permission`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `role_permission`
--
ALTER TABLE `role_permission`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=351;

--
-- AUTO_INCREMENT for table `trip`
--
ALTER TABLE `trip`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `trip_item`
--
ALTER TABLE `trip_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `trip_party`
--
ALTER TABLE `trip_party`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor`
--
ALTER TABLE `vendor`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
