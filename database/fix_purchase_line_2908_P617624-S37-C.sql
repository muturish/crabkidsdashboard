-- Fix: unblock sale of P617624-S37-C (variation_id=798, product_id=131).
--
-- Diagnosis (from crabkidskenyaco_pos (2).sql, 19 Aug 2026 dump): every
-- purchase batch for this SKU reconciles exactly against its FIFO sales
-- allocations, with zero returns anywhere:
--   batch 2908: qty=6, quantity_sold=6, adjusted=0, returned=0 -> remaining=0
--   batch 3720: qty=3, quantity_sold=3, adjusted=0, returned=0 -> remaining=0
--   batch 3838: qty=3, quantity_sold=3, adjusted=0, returned=0 -> remaining=0
--   batch 4057: qty=2, quantity_sold=2, adjusted=0, returned=0 -> remaining=0
--   (14 purchased = 14 sold via transaction_sell_lines_purchase_lines,
--    matched line-for-line, no qty_returned on any allocation or sell line,
--    no stock_adjustment_lines rows for this variation)
-- Yet variation_location_details shows 1 unit physically available at
-- location_id=1 (id=1056) that none of the above explains. Same situation
-- as fix_purchase_line_1082_star_mall.sql: no specific event accounts for
-- the extra unit, so this frees up 1 unit of capacity on the oldest batch
-- (id=2908, received 2026-05-08) rather than fabricating history.
--
-- Current state of batch 2908: quantity=6, quantity_sold=6, remaining=0
-- After fix: quantity_sold=5, remaining=1 (matches the 1 physically available unit)
--
-- ALWAYS take a fresh database backup before running this against production.

START TRANSACTION;

-- 1. Confirm current (broken) state before changing anything.
SELECT id, product_id, variation_id, quantity, quantity_sold, quantity_adjusted, quantity_returned
FROM purchase_lines
WHERE id = 2908 AND product_id = 131 AND variation_id = 798;

-- 2. Apply the correction.
UPDATE purchase_lines
SET quantity_sold = 5
WHERE id = 2908
  AND product_id = 131
  AND variation_id = 798
  AND quantity_sold = 6;   -- guard: only apply if it's still in the exact broken state diagnosed above

-- 3. Confirm exactly one row was updated.
SELECT ROW_COUNT() AS rows_updated;

-- 4. Re-check the row after the fix.
SELECT id, quantity, quantity_sold, quantity_adjusted, quantity_returned,
       (quantity - quantity_sold - quantity_adjusted - quantity_returned) AS remaining
FROM purchase_lines
WHERE id = 2908 AND product_id = 131 AND variation_id = 798;

-- If rows_updated = 1 and remaining = 1 above, run:
-- COMMIT;
--
-- If anything looks off, run:
-- ROLLBACK;
