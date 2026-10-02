-- Product features: SKU, old price (discount), stock, specifications,
-- delivery + returns notes, variants (size/colour/storage…), wishlists,
-- and the quantity/variant a plan was bought with.
-- Works on MySQL 5.7/8.x and MariaDB, and is safe to run more than once.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-03-product-features.sql

SET @db := DATABASE();

-- ---------- products: new columns ----------
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'sku') = 0,
  'ALTER TABLE products ADD COLUMN sku VARCHAR(64) DEFAULT NULL AFTER name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'compare_at_pesewas') = 0,
  'ALTER TABLE products ADD COLUMN compare_at_pesewas INT UNSIGNED DEFAULT NULL AFTER cash_price_pesewas', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'stock') = 0,
  'ALTER TABLE products ADD COLUMN stock INT UNSIGNED DEFAULT NULL AFTER compare_at_pesewas', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'specs') = 0,
  'ALTER TABLE products ADD COLUMN specs TEXT DEFAULT NULL AFTER description', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'delivery_info') = 0,
  'ALTER TABLE products ADD COLUMN delivery_info TEXT DEFAULT NULL AFTER specs', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'return_policy') = 0,
  'ALTER TABLE products ADD COLUMN return_policy TEXT DEFAULT NULL AFTER delivery_info', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'option1_name') = 0,
  'ALTER TABLE products ADD COLUMN option1_name VARCHAR(40) NOT NULL DEFAULT '''' AFTER plan_frequencies', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'option2_name') = 0,
  'ALTER TABLE products ADD COLUMN option2_name VARCHAR(40) NOT NULL DEFAULT '''' AFTER option1_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND COLUMN_NAME = 'option3_name') = 0,
  'ALTER TABLE products ADD COLUMN option3_name VARCHAR(40) NOT NULL DEFAULT '''' AFTER option2_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_sku') = 0,
  'ALTER TABLE products ADD KEY idx_products_sku (sku)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------- product_variants ----------
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

-- ---------- wishlists ----------
CREATE TABLE IF NOT EXISTS wishlists (
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  KEY idx_wishlists_product (product_id),
  CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- plans: what exactly was bought ----------
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'quantity') = 0,
  'ALTER TABLE plans ADD COLUMN quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER customer_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- No foreign key: a merchant may delete a variant later; the label below keeps the record.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'variant_id') = 0,
  'ALTER TABLE plans ADD COLUMN variant_id INT UNSIGNED DEFAULT NULL AFTER quantity', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'variant_label') = 0,
  'ALTER TABLE plans ADD COLUMN variant_label VARCHAR(190) NOT NULL DEFAULT '''' AFTER variant_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1 once the plan's stock has been taken off the shelf (set on activation,
-- cleared on cancellation) — makes both moves idempotent.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'stock_reserved') = 0,
  'ALTER TABLE plans ADD COLUMN stock_reserved TINYINT(1) NOT NULL DEFAULT 0 AFTER variant_label', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
