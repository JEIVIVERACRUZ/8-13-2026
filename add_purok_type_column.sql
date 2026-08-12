-- Run this in phpMyAdmin (SQL tab) on barangay_db

ALTER TABLE `puroks`
  ADD COLUMN `type` ENUM('zone','marker') NOT NULL DEFAULT 'zone' AFTER `name`;
