-- Email addresses for customers and shops (asked for at sign-up).
-- Accounts made before this have none (NULL) and can add one from their
-- account / shop settings page. Works on MySQL 5.7/8.x and MariaDB, and is
-- safe to run more than once.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-07-emails.sql

SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email') = 0,
  'ALTER TABLE users ADD COLUMN email VARCHAR(190) DEFAULT NULL AFTER phone', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_email') = 0,
  'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'merchants' AND COLUMN_NAME = 'email') = 0,
  'ALTER TABLE merchants ADD COLUMN email VARCHAR(190) DEFAULT NULL AFTER phone', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'merchants' AND INDEX_NAME = 'uq_merchants_email') = 0,
  'ALTER TABLE merchants ADD UNIQUE KEY uq_merchants_email (email)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
