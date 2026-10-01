-- Switch payments from Moolre to Paystack. MariaDB syntax (ADD COLUMN IF NOT
-- EXISTS), safe to run more than once. Fresh installs get these columns from
-- schema.sql already.
--   mysql -u pss -p paysmallsmall < database/migrations/2026-10-01-paystack.sql

ALTER TABLE merchants
  ADD COLUMN IF NOT EXISTS payout_bank_code VARCHAR(20) NOT NULL DEFAULT '' AFTER payout_number,
  ADD COLUMN IF NOT EXISTS paystack_recipient_code VARCHAR(40) NOT NULL DEFAULT '' AFTER payout_bank_code;

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
