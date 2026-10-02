-- Performance: indexes for the shop listing, product page and back-office
-- queries. Adds indexes only — no data changes. Works on MySQL 5.7/8.x and
-- MariaDB, and is safe to run more than once.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-06-performance-indexes.sql

SET @db := DATABASE();

-- Shop grid / category pages: active products, newest first, per category.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_listing') = 0,
  'ALTER TABLE products ADD KEY idx_products_listing (active, category, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'products' AND INDEX_NAME = 'idx_products_created') = 0,
  'ALTER TABLE products ADD KEY idx_products_created (active, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Only approved shops are listed.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'merchants' AND INDEX_NAME = 'idx_merchants_status') = 0,
  'ALTER TABLE merchants ADD KEY idx_merchants_status (status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- "N paying" counts on every product card: plans per product by status.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'plans' AND INDEX_NAME = 'idx_plans_product_status') = 0,
  'ALTER TABLE plans ADD KEY idx_plans_product_status (product_id, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- My plans: a customer's plans by status.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'plans' AND INDEX_NAME = 'idx_plans_customer_status') = 0,
  'ALTER TABLE plans ADD KEY idx_plans_customer_status (customer_id, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Star ratings on every card: published reviews per product.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'idx_reviews_product_status') = 0,
  'ALTER TABLE reviews ADD KEY idx_reviews_product_status (product_id, status, rating)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reconcile job + refund/payout checks: pending transactions, per installment.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'transactions' AND INDEX_NAME = 'idx_transactions_status') = 0,
  'ALTER TABLE transactions ADD KEY idx_transactions_status (status, created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'transactions' AND INDEX_NAME = 'idx_transactions_installment') = 0,
  'ALTER TABLE transactions ADD KEY idx_transactions_installment (installment_id, type, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reminders: unpaid installments coming due.
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'installments' AND INDEX_NAME = 'idx_installments_unpaid_due') = 0,
  'ALTER TABLE installments ADD KEY idx_installments_unpaid_due (paid_at, due_date)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Refresh the optimiser's statistics so it starts using the new indexes.
ANALYZE TABLE products, merchants, plans, reviews, transactions, installments;
