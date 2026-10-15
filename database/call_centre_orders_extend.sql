-- ============================================================================
-- Call Center Orders: update the structure of `call_centre_orders`
-- ============================================================================
--
-- Extends the existing `call_centre_orders` table in the POS database with the
-- columns the dashboard's Call Center Orders pages need, and creates
-- `call_centre_order_items` for the order lines.
--
-- HOW TO RUN (live server):
--   1. cPanel -> phpMyAdmin -> select the POS database (crabkidskenyaco_pos)
--   2. SQL tab -> paste this whole file -> Go
--
-- SAFE TO RE-RUN: every step checks first and only adds what is missing, so
-- running it twice (or after the dashboard already updated the table) does
-- nothing. It never deletes or changes existing rows.
--
-- The dashboard also applies the same changes automatically the first time a
-- Call Center page opens (ensure_call_center_orders_table() in
-- includes/data.php) -- this script is for when the dashboard's database user
-- isn't allowed to ALTER/CREATE tables.
--
-- Requires MySQL 8.0+ (or MariaDB 10.3+).
-- ============================================================================


-- ── 1. Base table (only created if it doesn't exist at all) ─────────────────

CREATE TABLE IF NOT EXISTS `call_centre_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `delivery_address` TEXT NULL,
  `mode_of_delivery` VARCHAR(100) NULL,
  `mode_of_payment` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ── 2. Extra columns (each added only if missing) ───────────────────────────

SET @t = 'call_centre_orders';

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'business_id') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `business_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'order_no') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `order_no` VARCHAR(20) NULL AFTER `business_id`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'payment_status') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `payment_status` ENUM(''paid'',''pod'') NOT NULL DEFAULT ''pod'' AFTER `mode_of_payment`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'mpesa_receipt_number') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `mpesa_receipt_number` VARCHAR(50) NULL AFTER `payment_status`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'order_status') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `order_status` ENUM(''pending'',''converted'',''cancelled'') NOT NULL DEFAULT ''pending'' AFTER `mpesa_receipt_number`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'subtotal_amount') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `subtotal_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `order_status`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'discount_amount') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `subtotal_amount`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'shipping_amount') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `shipping_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `discount_amount`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'total_amount') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `shipping_amount`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'agent_name') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `agent_name` VARCHAR(100) NULL AFTER `total_amount`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'notes_updated_at') = 0,
  'ALTER TABLE `call_centre_orders` ADD COLUMN `notes_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `notes`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- The original `item` text column is no longer used (products are stored in
-- call_centre_order_items) -- drop it if it is still there.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'item') > 0,
  'ALTER TABLE `call_centre_orders` DROP COLUMN `item`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ── 3. Indexes (each added only if missing) ─────────────────────────────────

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @t AND index_name = 'order_no') = 0,
  'ALTER TABLE `call_centre_orders` ADD UNIQUE KEY `order_no` (`order_no`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- One M-Pesa code per order. Fails only if existing rows already share a
-- code -- fix those rows first, then re-run.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @t AND index_name = 'mpesa_receipt_number') = 0,
  'ALTER TABLE `call_centre_orders` ADD UNIQUE KEY `mpesa_receipt_number` (`mpesa_receipt_number`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @t AND index_name = 'business_id') = 0,
  'ALTER TABLE `call_centre_orders` ADD KEY `business_id` (`business_id`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = @t AND index_name = 'order_status') = 0,
  'ALTER TABLE `call_centre_orders` ADD KEY `order_status` (`order_status`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;


-- ── 4. Order lines table ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `call_centre_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `variation_id` INT UNSIGNED NULL,
  `product_name` VARCHAR(191) NOT NULL,
  `variation_name` VARCHAR(191) NULL,
  `sku` VARCHAR(100) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ── 5. Check: shows the final structure ─────────────────────────────────────

SHOW COLUMNS FROM `call_centre_orders`;
