-- Fix: purchase_lines.quantity_sold was never decremented after a customer
-- return on 2026-02-26 (sell_line_id=451, qty=1, fully returned), leaving
-- purchase batch #1222 showing as fully sold (8/8) when 1 unit is actually
-- back in stock. This blocks new sales of SKU 617611-S33-B with:
--   "ERROR: NOT ALLOWED: Mismatch between sold and purchase quantity"
--
-- Evidence:
--   purchase_lines id=1222: quantity=8, quantity_sold=8 (should be 7)
--   transaction_sell_lines_purchase_lines: sum(quantity) allocated = 8,
--     of which 1 (sell_line_id=451) has qty_returned=1.0000
--   variation_location_details: qty_available at location 1 = 1
--     (8 - 7 - 0 adjusted - 0 returned-to-supplier = 1, matches exactly)
--
-- ALWAYS take a fresh database backup before running this against production.

START TRANSACTION;

-- 1. Confirm current (broken) state before changing anything
SELECT id, product_id, variation_id, quantity, quantity_sold, quantity_adjusted, quantity_returned
FROM purchase_lines
WHERE id = 1222 AND product_id = 8 AND variation_id = 55;

-- 2. Apply the correction
UPDATE purchase_lines
SET quantity_sold = 7
WHERE id = 1222
  AND product_id = 8
  AND variation_id = 55
  AND quantity_sold = 8;   -- guard: only apply if it's still in the exact broken state we diagnosed

-- 3. Confirm exactly one row was updated
SELECT ROW_COUNT() AS rows_updated;

-- 4. Re-check the row after the fix
SELECT id, product_id, variation_id, quantity, quantity_sold, quantity_adjusted, quantity_returned
FROM purchase_lines
WHERE id = 1222 AND product_id = 8 AND variation_id = 55;

-- If rows_updated = 1 and the numbers above look right, run:
-- COMMIT;
--
-- If anything looks off, run:
-- ROLLBACK;
