-- Switch payments from Moolre to Paystack: add the merchant payout columns.
-- Works on MySQL 5.7/8.x and MariaDB, and is safe to run more than once (each
-- column is only added if it's missing). Fresh installs already get these
-- columns from schema.sql.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-01-paystack.sql

SET @db := DATABASE();

-- payout_bank_code: MoMo network (MTN | VOD | ATL) or GhIPSS bank code.
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'merchants' AND COLUMN_NAME = 'payout_bank_code') = 0,
  'ALTER TABLE merchants ADD COLUMN payout_bank_code VARCHAR(20) NOT NULL DEFAULT '''' AFTER payout_number',
  'SELECT ''payout_bank_code already exists'' AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- paystack_recipient_code: cached Paystack transfer recipient (RCP_...).
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'merchants' AND COLUMN_NAME = 'paystack_recipient_code') = 0,
  'ALTER TABLE merchants ADD COLUMN paystack_recipient_code VARCHAR(40) NOT NULL DEFAULT '''' AFTER payout_bank_code',
  'SELECT ''paystack_recipient_code already exists'' AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing MoMo merchants: default their network from the number's prefix.
-- They can correct it in Shop settings (ported numbers keep the old prefix).
UPDATE merchants
SET payout_bank_code = CASE
    WHEN SUBSTRING(payout_number, 4, 2) IN ('24','25','53','54','55','59') THEN 'MTN'
    WHEN SUBSTRING(payout_number, 4, 2) IN ('20','50') THEN 'VOD'
    WHEN SUBSTRING(payout_number, 4, 2) IN ('26','27','56','57') THEN 'ATL'
    ELSE ''
  END
WHERE payout_channel = 'momo' AND payout_bank_code = '' AND payout_number LIKE '233%';
