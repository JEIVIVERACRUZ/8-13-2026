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
