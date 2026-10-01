-- Payment options per product + pay-in-full.
--   * products.plan_frequencies: which installment schedules the merchant
--     allows (comma list of daily,weekly,monthly). Paying in full is always on.
--   * plans.frequency gains 'once' (a plan paid in full in one payment).
-- Works on MySQL 5.7/8.x and MariaDB, and is safe to run more than once.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-02-payment-options.sql

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'plan_frequencies') = 0,
  'ALTER TABLE products ADD COLUMN plan_frequencies VARCHAR(30) NOT NULL DEFAULT ''daily,weekly,monthly'' AFTER category',
  'SELECT ''plan_frequencies already exists'' AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Re-declaring the same ENUM is harmless, so this can run every time.
ALTER TABLE plans
  MODIFY frequency ENUM('daily','weekly','monthly','once') NOT NULL DEFAULT 'weekly';
