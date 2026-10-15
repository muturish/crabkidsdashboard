-- Drops and recreates call_centre_orders. WARNING: deletes all orders in it.

DROP TABLE IF EXISTS `call_centre_orders`;

CREATE TABLE `call_centre_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `order_no` VARCHAR(20) NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `delivery_address` TEXT NULL,
  `mode_of_delivery` VARCHAR(100) NULL,
  `mode_of_payment` VARCHAR(100) NULL,
  `payment_status` ENUM('paid','pod') NOT NULL DEFAULT 'pod',
  `mpesa_receipt_number` VARCHAR(50) NULL,
  `order_status` ENUM('pending','converted','cancelled') NOT NULL DEFAULT 'pending',
  `subtotal_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `shipping_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `agent_name` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `notes_updated_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  UNIQUE KEY `mpesa_receipt_number` (`mpesa_receipt_number`),
  KEY `business_id` (`business_id`),
  KEY `order_status` (`order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
