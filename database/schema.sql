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
  -- Set once the customer proves they own the number (SMS code).
  phone_verified_at DATETIME DEFAULT NULL,
  -- MoMo wallet for direct prompts (NULL = the account phone) and its network.
  momo_number VARCHAR(12) DEFAULT NULL,
  momo_network VARCHAR(5) DEFAULT NULL,
  -- Remember cards paid with (Paystack tokens only, never card numbers).
  save_cards TINYINT(1) NOT NULL DEFAULT 1,
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
  -- Paystack payout target: MoMo network (MTN | VOD | ATL) or GhIPSS bank code.
  payout_bank_code VARCHAR(20) NOT NULL DEFAULT '',
  -- Cached Paystack transfer recipient (RCP_...); cleared when payout details change.
  paystack_recipient_code VARCHAR(40) NOT NULL DEFAULT '',
  status ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
  -- Admin's reason when declining (shown to the shop owner).
  review_note VARCHAR(255) NOT NULL DEFAULT '',
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
  sku VARCHAR(64) DEFAULT NULL,
  description TEXT NOT NULL,
  -- "Key: Value" lines, shown as a specifications table.
  specs TEXT DEFAULT NULL,
  delivery_info TEXT DEFAULT NULL,
  return_policy TEXT DEFAULT NULL,
  photo VARCHAR(255) NOT NULL DEFAULT '',
  cash_price_pesewas INT UNSIGNED NOT NULL,
  -- Old price, shown struck through when higher than the price.
  compare_at_pesewas INT UNSIGNED DEFAULT NULL,
  -- NULL = not tracked (always available). Ignored when the product has variants.
  stock INT UNSIGNED DEFAULT NULL,
  category VARCHAR(60) NOT NULL DEFAULT 'general',
  -- Installment schedules the merchant allows (paying in full is always allowed).
  plan_frequencies VARCHAR(30) NOT NULL DEFAULT 'daily,weekly,monthly',
  -- Variant option names, e.g. Colour / Storage / Size ('' = unused).
  option1_name VARCHAR(40) NOT NULL DEFAULT '',
  option2_name VARCHAR(40) NOT NULL DEFAULT '',
  option3_name VARCHAR(40) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_merchant (merchant_id),
  KEY idx_products_sku (sku),
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

-- One row per sellable combination (e.g. Black / 128GB). NULL price = the
-- product's price; NULL stock = not tracked (always available).
CREATE TABLE IF NOT EXISTS product_variants (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  option1 VARCHAR(60) NOT NULL DEFAULT '',
  option2 VARCHAR(60) NOT NULL DEFAULT '',
  option3 VARCHAR(60) NOT NULL DEFAULT '',
  sku VARCHAR(64) DEFAULT NULL,
  price_pesewas INT UNSIGNED DEFAULT NULL,
  stock INT UNSIGNED DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_variants_product (product_id),
  KEY idx_variants_sku (sku),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  -- No FK: variants can be deleted later; variant_label keeps what was bought.
  variant_id INT UNSIGNED DEFAULT NULL,
  variant_label VARCHAR(190) NOT NULL DEFAULT '',
  -- 1 once stock was taken for this plan (on activation); cleared on cancel.
  stock_reserved TINYINT(1) NOT NULL DEFAULT 0,
  total_pesewas INT UNSIGNED NOT NULL,
  installment_pesewas INT UNSIGNED NOT NULL,
  -- once = paid in full in a single payment.
  frequency ENUM('daily','weekly','monthly','once') NOT NULL DEFAULT 'weekly',
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
  provider_ref VARCHAR(64) NOT NULL,       -- our unique reference sent to Paystack
  external_ref VARCHAR(64) NOT NULL DEFAULT '',  -- Paystack id: access code / transaction id / transfer code / refund id
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
  -- pending = waiting for an admin; only 'approved' shows publicly and counts in ratings.
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  moderation_note VARCHAR(255) NOT NULL DEFAULT '',  -- why rejected/hidden (shown to the author)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_product_user (product_id, user_id),
  KEY idx_reviews_product (product_id),
  KEY idx_reviews_status (status),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Customer photos on a review. Each one is checked by an admin before it shows.
CREATE TABLE IF NOT EXISTS review_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  review_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_review_photos_review (review_id),
  KEY idx_review_photos_status (status),
  CONSTRAINT fk_review_photos_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- "Report this review". One report per customer per review.
CREATE TABLE IF NOT EXISTS review_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  review_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  reason VARCHAR(20) NOT NULL,             -- spam | offensive | fake | off_topic | private_info | other
  note VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_reports_user (review_id, user_id),
  KEY idx_review_reports_status (status),
  CONSTRAINT fk_review_reports_review FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Saved items ("wishlist"), one row per customer per product.
CREATE TABLE IF NOT EXISTS wishlists (
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  KEY idx_wishlists_product (product_id),
  CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- One-time SMS codes: verify a number, reset a PIN/password, change number.
-- Only a hash of the code is stored.
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

-- Wrong PIN/password attempts; 5 in 15 minutes locks that number briefly.
CREATE TABLE IF NOT EXISTS login_failures (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(12) NOT NULL,
  role VARCHAR(10) NOT NULL,               -- customer | merchant
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_failures (phone, role, created_at)
) ENGINE=InnoDB;

-- Customer delivery addresses.
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

-- Saved cards: Paystack reusable authorizations (tokens), never card numbers.
-- email = the address the card was first charged with; Paystack needs the
-- same one to charge it again.
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
