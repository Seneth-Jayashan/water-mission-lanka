-- Run this migration to add description and image columns to the categories table
-- Execute via: mysql -u root water_mission < backend/migrate_categories.sql

ALTER TABLE `categories` ADD COLUMN IF NOT EXISTS `description` TEXT AFTER `name`;
ALTER TABLE `categories` ADD COLUMN IF NOT EXISTS `image` VARCHAR(255) NULL AFTER `description`;
