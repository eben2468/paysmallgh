-- Customer accounts: phone verification, one-time codes (verify / reset /
-- change number), saved addresses, saved cards and MoMo wallet, plus
-- merchant decline-with-reason.
-- Works on MySQL 5.7/8.x and MariaDB, and is safe to run more than once.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-04-accounts.sql

SET @db := DATABASE();

-- ---------- users ----------
-- Customers who signed up before verification existed count as verified
-- (set once, only when the column is first added).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone_verified_at') = 0,
  'ALTER TABLE users ADD COLUMN phone_verified_at DATETIME DEFAULT NULL AFTER pin_hash', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@sql LIKE 'ALTER%', 'UPDATE users SET phone_verified_at = created_at WHERE phone_verified_at IS NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- MoMo wallet for "send a prompt to my phone" (NULL = the account phone).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'momo_number') = 0,
  'ALTER TABLE users ADD COLUMN momo_number VARCHAR(12) DEFAULT NULL AFTER phone_verified_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'momo_network') = 0,
  'ALTER TABLE users ADD COLUMN momo_network VARCHAR(5) DEFAULT NULL AFTER momo_number', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remember cards paid with (Paystack tokens only — never card numbers).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'save_cards') = 0,
  'ALTER TABLE users ADD COLUMN save_cards TINYINT(1) NOT NULL DEFAULT 1 AFTER momo_network', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- one-time codes (SMS) ----------
CREATE TABLE IF NOT EXISTS otp_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(12) NOT NULL,
  purpose VARCHAR(20) NOT NULL,            -- verify | reset | merchant_reset | change_phone
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_otp_phone_purpose (phone, purpose, created_at)
) ENGINE=InnoDB;

-- ---------- saved addresses ----------
CREATE TABLE IF NOT EXISTS user_addresses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT '',   -- Home, Work, Mum's house…
  recipient VARCHAR(120) NOT NULL,
  phone VARCHAR(12) NOT NULL,
  region VARCHAR(40) NOT NULL,
  town VARCHAR(80) NOT NULL,
  area VARCHAR(160) NOT NULL,              -- street / area / house number
  landmark VARCHAR(160) NOT NULL DEFAULT '',
  gps VARCHAR(20) NOT NULL DEFAULT '',     -- GhanaPost GPS, e.g. GA-123-4567
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- saved cards (Paystack reusable authorizations) ----------
-- email: the address the card was first charged with — Paystack needs the
-- same one to charge it again, even if the customer changes number later.
CREATE TABLE IF NOT EXISTS saved_cards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  authorization_code VARCHAR(100) NOT NULL,
  signature VARCHAR(100) NOT NULL,
  email VARCHAR(160) NOT NULL,
  brand VARCHAR(30) NOT NULL DEFAULT '',
  last4 CHAR(4) NOT NULL DEFAULT '',
  exp_month CHAR(2) NOT NULL DEFAULT '',
  exp_year CHAR(4) NOT NULL DEFAULT '',
  bank VARCHAR(80) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_saved_cards_user_signature (user_id, signature),
  CONSTRAINT fk_saved_cards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- merchants: decline with a reason ----------
ALTER TABLE merchants
  MODIFY status ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'merchants' AND COLUMN_NAME = 'review_note') = 0,
  'ALTER TABLE merchants ADD COLUMN review_note VARCHAR(255) NOT NULL DEFAULT '''' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- login throttling ----------
-- One row per wrong PIN/password; 5 in 15 minutes locks that number briefly.
CREATE TABLE IF NOT EXISTS login_failures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(12) NOT NULL,
  role VARCHAR(10) NOT NULL,               -- customer | merchant
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_failures (phone, role, created_at)
) ENGINE=InnoDB;
