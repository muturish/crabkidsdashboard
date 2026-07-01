-- Set every product's low-stock alert threshold to 2 units.
-- Target: crabkidskenyaco_pos database, business_id = 1 (CrabKids Kenya).
--
-- IMPORTANT: back up first. In phpMyAdmin: select the database ->
-- Export -> Quick -> Go. On the command line:
--   mysqldump -u <user> -p crabkidskenyaco_pos > backup_before_alert_qty.sql

-- 1. Preview what will change (run this first, review the output).
SELECT id, name, alert_quantity
FROM products
WHERE business_id = 1
ORDER BY name;

-- 2. Apply the update — every product's alert_quantity becomes 2.
UPDATE products
SET alert_quantity = 2
WHERE business_id = 1;

-- 3. Verify.
SELECT COUNT(*) AS total_products,
       SUM(alert_quantity = 2) AS now_at_2
FROM products
WHERE business_id = 1;
