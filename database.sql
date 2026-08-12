-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2026 at 06:22 PM
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
-- Database: `barangay_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_type` enum('admin','resident') DEFAULT 'admin',
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(200) NOT NULL,
  `module` varchar(80) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Administrator','Staff') NOT NULL DEFAULT 'Staff',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
-- password: 12345687

INSERT INTO `admin_users` (`id`, `name`, `email`, `username`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Barangay Administrator', 'admin@barangaysanisidro.gov.ph', 'admin', '$2y$10$hQfFTo7HMXZkAaxYP08BTO08p8x6N.hqw3j3tTS2A.FDrnI0eUOoW', 'Administrator', 'Active', '2026-06-22 00:17:49', '2026-06-22 00:17:49');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `category` enum('Event','Service Update','Advisory','Important Notice','Other') DEFAULT 'Other',
  `color` varchar(20) DEFAULT 'primary',
  `publish_date` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `description`, `category`, `color`, `publish_date`, `created_by`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 'Fiesta Celebration', 'Join us for the Feast of St. Isidore on May 25, 2026 at the barangay plaza...', 'Event', 'primary', '2026-05-22', NULL, 1, '2026-06-22 00:18:03', '2026-06-22 00:18:03'),
(5, 'Health Clinic Schedule', 'Free health check-up every Saturday 8AM to 12PM...', 'Service Update', 'info', '2026-05-19', NULL, 1, '2026-06-22 00:18:03', '2026-06-22 00:18:03'),
(6, 'Curfew Advisory', 'Residents are advised to stay at home during flood alerts...', 'Advisory', 'warning', '2026-05-21', NULL, 1, '2026-06-22 00:18:03', '2026-06-22 00:18:03');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `purpose` varchar(200) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` varchar(40) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed','Rescheduled') DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangay_settings`
--

CREATE TABLE `barangay_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangay_settings`
--

INSERT INTO `barangay_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('address', '123 Sampaguita St., Zone 2, San Isidro', '2026-06-05 16:38:48'),
('barangay_name', 'Barangayyayayayay', '2026-06-11 16:36:00'),
('contact_number', '(02) 111111111111', '2026-06-11 00:40:59'),
('email', 'info@barangaysanisidro.gov.ph', '2026-06-05 16:38:48'),
('logo_path', 'assets/images/logo_custom.jpg', '2026-06-06 16:15:54'),
('resident_id_seq', '0', '2026-06-21 23:57:42'),
('resident_id_year', '2026', '2026-06-05 16:38:48'),
('theme_color', '#0066cc', '2026-06-11 16:33:21');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_number` varchar(20) NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('Barangay Clearance','Certificate of Residency','Barangay ID','Certificate of Indigency','Business Permit','Proof of Residency','Other') NOT NULL,
  `purpose` varchar(250) DEFAULT NULL,
  `status` enum('Pending','Processing','Ready for Pickup','Completed','Rejected') DEFAULT 'Pending',
  `requested_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `head_requests`
--

CREATE TABLE `head_requests` (
  `id` int(11) NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `house_number` varchar(100) DEFAULT NULL,
  `zone_purok` varchar(100) DEFAULT NULL,
  `street_address` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Run this in phpMyAdmin on barangay_db
--

CREATE TABLE IF NOT EXISTS `document_templates` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_type` varchar(100) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `field_positions` longtext DEFAULT NULL COMMENT 'JSON: {field_name: {top, left, font_size, bold}}',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-insert all document types so they show up in the admin page immediately
INSERT INTO `document_templates` (`document_type`) VALUES
('Barangay Clearance'),
('Certificate of Residency'),
('Barangay ID'),
('Certificate of Indigency'),
('Business Permit'),
('Proof of Residency'),
('Other')
ON DUPLICATE KEY UPDATE document_type = document_type;

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `id` int(10) UNSIGNED NOT NULL,
  `household_head` int(10) UNSIGNED DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `zone_purok` varchar(60) DEFAULT NULL,
  `house_number` varchar(30) DEFAULT NULL,
  `member_count` int(10) UNSIGNED DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_members`
--

CREATE TABLE `household_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `household_id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(160) NOT NULL,
  `relation` varchar(60) NOT NULL,
  `age` tinyint(3) UNSIGNED DEFAULT NULL,
  `status` enum('Verified','Pending','Inactive') DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_requests`
--

CREATE TABLE `household_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `request_type` enum('Head','Join') NOT NULL DEFAULT 'Head',
  `house_number` varchar(30) DEFAULT NULL,
  `zone_purok` varchar(60) DEFAULT NULL,
  `household_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `remarks` varchar(250) DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `puroks`
--

CREATE TABLE `puroks` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(250) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#34d399',
  `coordinates` longtext DEFAULT NULL,
  `resident_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(10) UNSIGNED NOT NULL,
  `barangay_id` varchar(20) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `street_address` varchar(200) DEFAULT NULL,
  `zone_purok` varchar(60) DEFAULT NULL,
  `house_number` varchar(30) DEFAULT NULL,
  `occupation` varchar(120) DEFAULT NULL,
  `workplace` varchar(150) DEFAULT NULL,
  `years_of_residency` int(10) UNSIGNED DEFAULT 0,
  `is_household_head` tinyint(1) DEFAULT 0,
  `household_id` int(10) UNSIGNED DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('Verified','Pending','Inactive') NOT NULL DEFAULT 'Pending',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_dashboard_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_dashboard_stats` (
`total_residents` bigint(21)
,`family_heads` bigint(21)
,`total_households` bigint(21)
,`pending_requests` bigint(21)
,`total_requests` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_residents`
-- (See below for the actual view)
--
CREATE TABLE `v_residents` (
`id` int(10) unsigned
,`barangay_id` varchar(20)
,`full_name` varchar(161)
,`first_name` varchar(80)
,`last_name` varchar(80)
,`email` varchar(180)
,`phone` varchar(30)
,`date_of_birth` date
,`age` bigint(21)
,`gender` enum('Male','Female','Other')
,`street_address` varchar(200)
,`zone_purok` varchar(60)
,`house_number` varchar(30)
,`occupation` varchar(120)
,`workplace` varchar(150)
,`years_of_residency` int(10) unsigned
,`is_household_head` tinyint(1)
,`household_id` int(10) unsigned
,`status` enum('Verified','Pending','Inactive')
,`latitude` decimal(10,7)
,`longitude` decimal(10,7)
,`created_at` datetime
);

-- Run this in phpMyAdmin (SQL tab) on barangay_db
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','resident') NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_lookup` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Run this in phpMyAdmin (SQL tab) on barangay_db

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','resident') NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `selector` varchar(24) NOT NULL,
  `validator_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_lookup` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Run this in phpMyAdmin (SQL tab) on barangay_db

CREATE TABLE IF NOT EXISTS `household_invites` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `household_id` int(10) UNSIGNED NOT NULL,
  `resident_id` int(10) UNSIGNED NOT NULL,
  `invited_by` int(10) UNSIGNED NOT NULL,
  `relation` varchar(60) NOT NULL,
  `status` enum('Pending','Accepted','Declined') NOT NULL DEFAULT 'Pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `household_id` (`household_id`),
  KEY `resident_id` (`resident_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Run this in phpMyAdmin (SQL tab) on barangay_db

ALTER TABLE `puroks`
  ADD COLUMN `type` ENUM('zone','marker') NOT NULL DEFAULT 'zone' AFTER `name`;

--
-- Structure for view `v_dashboard_stats`
--
DROP TABLE IF EXISTS `v_dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dashboard_stats`  AS SELECT (select count(0) from `residents` where `residents`.`status` <> 'Inactive') AS `total_residents`, (select count(0) from `residents` where `residents`.`is_household_head` = 1) AS `family_heads`, (select count(0) from `households`) AS `total_households`, (select count(0) from `document_requests` where `document_requests`.`status` in ('Pending','Processing')) AS `pending_requests`, (select count(0) from `document_requests`) AS `total_requests` ;

-- --------------------------------------------------------

--
-- Structure for view `v_residents`
--
DROP TABLE IF EXISTS `v_residents`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_residents`  AS SELECT `r`.`id` AS `id`, `r`.`barangay_id` AS `barangay_id`, concat(`r`.`first_name`,' ',`r`.`last_name`) AS `full_name`, `r`.`first_name` AS `first_name`, `r`.`last_name` AS `last_name`, `r`.`email` AS `email`, `r`.`phone` AS `phone`, `r`.`date_of_birth` AS `date_of_birth`, timestampdiff(YEAR,`r`.`date_of_birth`,curdate()) AS `age`, `r`.`gender` AS `gender`, `r`.`street_address` AS `street_address`, `r`.`zone_purok` AS `zone_purok`, `r`.`house_number` AS `house_number`, `r`.`occupation` AS `occupation`, `r`.`workplace` AS `workplace`, `r`.`years_of_residency` AS `years_of_residency`, `r`.`is_household_head` AS `is_household_head`, `r`.`household_id` AS `household_id`, `r`.`status` AS `status`, `r`.`latitude` AS `latitude`, `r`.`longitude` AS `longitude`, `r`.`created_at` AS `created_at` FROM `residents` AS `r` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `barangay_settings`
--
ALTER TABLE `barangay_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `head_requests`
--
ALTER TABLE `head_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_household_address` (`house_number`,`zone_purok`);

--
-- Indexes for table `household_members`
--
ALTER TABLE `household_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `household_id` (`household_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `household_requests`
--
ALTER TABLE `household_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `household_id` (`household_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `puroks`
--
ALTER TABLE `puroks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barangay_id` (`barangay_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_resident_household` (`household_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `head_requests`
--
ALTER TABLE `head_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_members`
--
ALTER TABLE `household_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_requests`
--
ALTER TABLE `household_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `puroks`
--
ALTER TABLE `puroks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=0;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `head_requests`
--
ALTER TABLE `head_requests`
  ADD CONSTRAINT `head_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `household_members`
--
ALTER TABLE `household_members`
  ADD CONSTRAINT `household_members_ibfk_1` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `household_members_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `household_requests`
--
ALTER TABLE `household_requests`
  ADD CONSTRAINT `household_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `household_requests_ibfk_2` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `household_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `fk_resident_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
