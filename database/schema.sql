-- PaySmallSmall schema. MariaDB-compatible SQL only.
-- All money columns are pesewas (integers). Never floats.

CREATE DATABASE IF NOT EXISTS paysmallsmall
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE paysmallsmall;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(12) NOT NULL,              -- 233XXXXXXXXX
  pin_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS merchants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_name VARCHAR(160) NOT NULL,
  owner_name VARCHAR(120) NOT NULL,
  phone VARCHAR(12) NOT NULL,
  location VARCHAR(160) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  payout_channel ENUM('momo','bank') NOT NULL DEFAULT 'momo',
  payout_number VARCHAR(30) NOT NULL DEFAULT '',
  status ENUM('pending','approved','suspended') NOT NULL DEFAULT 'pending',
  -- KYC: Ghana Card number + uploaded card image (stored outside the webroot).
  id_number VARCHAR(32) NOT NULL DEFAULT '',
  id_card_path VARCHAR(255) NOT NULL DEFAULT '',
  business_reg VARCHAR(60) NOT NULL DEFAULT '',   -- optional business registration no.
  -- Verified = KYC checked by admin; shown to shoppers as a trust badge.
  verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_merchants_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  merchant_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  photo VARCHAR(255) NOT NULL DEFAULT '',
  cash_price_pesewas INT UNSIGNED NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'general',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_merchant (merchant_id),
  CONSTRAINT fk_products_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id)
) ENGINE=InnoDB;

-- Extra photos per product. products.photo stays the cover image (first one),
-- so cards and older single-photo products keep working unchanged.
CREATE TABLE IF NOT EXISTS product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_product_images_product (product_id),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  total_pesewas INT UNSIGNED NOT NULL,
  installment_pesewas INT UNSIGNED NOT NULL,
  frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  installments_total SMALLINT UNSIGNED NOT NULL,
  installments_paid SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  -- pending: created, first payment not yet confirmed. No payment, no plan.
  status ENUM('pending','active','completed','cancelled','defaulted') NOT NULL DEFAULT 'pending',
  grace_state ENUM('ok','grace','flagged') NOT NULL DEFAULT 'ok',
  grace_notified_at DATETIME DEFAULT NULL,
  payout_transaction_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  -- Set when the merchant confirms they've handed the item over (after payout).
  released_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_plans_customer (customer_id),
  KEY idx_plans_product (product_id),
  KEY idx_plans_status (status),
  CONSTRAINT fk_plans_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_plans_customer FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS installments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id INT UNSIGNED NOT NULL,
  number SMALLINT UNSIGNED NOT NULL,
  amount_pesewas INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  paid_at DATETIME DEFAULT NULL,
  -- Stamped when the "payment due soon" reminder SMS goes out (dedup guard).
  due_reminded_at DATETIME DEFAULT NULL,
  transaction_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_installments_plan_number (plan_id, number),
  KEY idx_installments_due (due_date),
  CONSTRAINT fk_installments_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
) ENGINE=InnoDB;

-- Append-only money ledger. Rows are inserted, then only status/raw_payload
-- are updated when the provider confirms. Never deleted.
CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type ENUM('collection','disbursement','refund') NOT NULL,
  status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  amount_pesewas INT UNSIGNED NOT NULL,
  phone VARCHAR(12) NOT NULL DEFAULT '',
  plan_id INT UNSIGNED DEFAULT NULL,
  installment_id INT UNSIGNED DEFAULT NULL,
  merchant_id INT UNSIGNED DEFAULT NULL,
  provider_ref VARCHAR(64) NOT NULL,       -- our unique reference sent to Moolre
  external_ref VARCHAR(64) NOT NULL DEFAULT '',  -- Moolre's transaction id
  raw_payload TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transactions_ref (provider_ref),
  KEY idx_transactions_plan (plan_id)
) ENGINE=InnoDB;

-- Product reviews. One review per customer per product (enforced by unique key).
CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,        -- 1..5
  body VARCHAR(600) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_product_user (product_id, user_id),
  KEY idx_reviews_product (product_id),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sms_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient VARCHAR(12) NOT NULL,
  body VARCHAR(480) NOT NULL,
  status ENUM('queued','sent','delivered','failed') NOT NULL DEFAULT 'queued',
  provider_ref VARCHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- USSD session state (gateway sends session id + accumulated input each hop)
CREATE TABLE IF NOT EXISTS ussd_sessions (
  id VARCHAR(64) NOT NULL,
  phone VARCHAR(12) NOT NULL,
  state VARCHAR(40) NOT NULL DEFAULT 'menu',
  context TEXT,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
