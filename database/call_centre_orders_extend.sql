-- Extends the existing `call_centre_orders` table in the POS database with the
-- columns the dashboard's Call Center Orders pages need, and adds the
-- `call_centre_order_items` table for order lines.
--
-- The dashboard also applies this automatically on first load of a Call Center
-- page (ensure_call_center_orders_table() in includes/data.php). Run this by
-- hand in phpMyAdmin only if the dashboard's DB user lacks ALTER/CREATE rights.
-- Not re-runnable: skip it if the columns already exist.

ALTER TABLE `call_centre_orders`
  ADD COLUMN `business_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`,
  ADD COLUMN `order_no` VARCHAR(20) NULL AFTER `business_id`,
  ADD COLUMN `payment_status` ENUM('paid','pod') NOT NULL DEFAULT 'pod' AFTER `mode_of_payment`,
  ADD COLUMN `mpesa_receipt_number` VARCHAR(50) NULL AFTER `payment_status`,
  ADD COLUMN `order_status` ENUM('pending','converted','cancelled') NOT NULL DEFAULT 'pending' AFTER `mpesa_receipt_number`,
  ADD COLUMN `subtotal_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `order_status`,
  ADD COLUMN `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `subtotal_amount`,
  ADD COLUMN `shipping_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `discount_amount`,
  ADD COLUMN `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER `shipping_amount`,
  ADD COLUMN `agent_name` VARCHAR(100) NULL AFTER `total_amount`,
  ADD COLUMN `notes_updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `notes`,
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD UNIQUE KEY `mpesa_receipt_number` (`mpesa_receipt_number`),
  ADD KEY `business_id` (`business_id`),
  ADD KEY `order_status` (`order_status`);

CREATE TABLE IF NOT EXISTS `call_centre_order_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `variation_id` INT UNSIGNED NULL,
  `product_name` VARCHAR(191) NOT NULL,
  `variation_name` VARCHAR(191) NULL,
  `sku` VARCHAR(100) NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
