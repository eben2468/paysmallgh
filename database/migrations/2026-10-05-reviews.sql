-- Review moderation: review status + admin note, review photos, reports.
-- Works on MySQL 5.7/8.x and MariaDB, and is safe to run more than once.
--
-- phpMyAdmin: select your database in the left panel, then Import this file.
-- Command line: mysql -u pss -p paysmallsmall < database/migrations/2026-10-05-reviews.sql

SET @db := DATABASE();

-- Existing reviews stay published (DEFAULT 'approved').
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE reviews ADD COLUMN status ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''approved'' AFTER body', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Why it was rejected / hidden (shown to the author).
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'moderation_note') = 0,
  'ALTER TABLE reviews ADD COLUMN moderation_note VARCHAR(255) NOT NULL DEFAULT '''' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE reviews ADD COLUMN updated_at DATETIME DEFAULT NULL AFTER created_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reviews' AND INDEX_NAME = 'idx_reviews_status') = 0,
  'ALTER TABLE reviews ADD KEY idx_reviews_status (status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
