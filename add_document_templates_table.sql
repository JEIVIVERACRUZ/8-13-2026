-- Run this in phpMyAdmin on barangay_db

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
