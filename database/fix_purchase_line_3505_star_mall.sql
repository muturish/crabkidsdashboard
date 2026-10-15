-- Fix: unblock sale of P617648-S37-C (variation_id=708, product_id=116) at
-- Star Mall (location_id=1).
--
-- Diagnosis (from crabkidskenyaco_pos.sql, 31 Aug 2026 dump): every purchase
-- batch for this SKU at Star Mall reconciles exactly against its FIFO sales
-- allocations, with zero returns anywhere:
--   batch 3505 (txn 179884, purchase_transfer): qty=3, sold=3, remaining=0
--   batch 3524 (txn 182692, purchase_transfer): qty=3, sold=3, remaining=0
--   batch 3718 (txn 211398, purchase_transfer): qty=3, sold=3, remaining=0
--   batch 3730 (txn 224021, purchase):          qty=1, sold=1, remaining=0
--   batch 3734 (txn 237076, purchase_transfer): qty=6, sold=6, remaining=0
--   batch 4051 (txn 285536, purchase_transfer): qty=3, sold=3, remaining=0
--   batch 4077 (txn 286920, purchase_transfer): qty=2, sold=2, remaining=0
--   (21 received = 21 sold via transaction_sell_lines_purchase_lines,
--    matched line-for-line, no qty_returned on any allocation or sell line,
--    no stock_adjustment_lines rows for this variation)
-- Yet variation_location_details shows 1 unit physically available at
-- location_id=1 (id=960) that none of the above explains. Same situation as
-- fix_purchase_line_1082_star_mall.sql and fix_purchase_line_2908_*.sql: no
-- specific event accounts for the extra unit, so this frees up 1 unit of
-- capacity on the oldest Star Mall batch (id=3505, received 2026-06-27)
-- rather than fabricating history.
--
-- Current state of batch 3505: quantity=3, quantity_sold=3, remaining=0
-- After fix: quantity_sold=2, remaining=1 (matches the 1 physically available unit)
--
-- ALWAYS take a fresh database backup before running this against production.
-- If more sales/purchases have happened since 31 Aug 2026, re-run the
-- diagnosis queries below first -- the guard clause will simply update 0
-- rows if batch 3505 is no longer in the exact state described above.

START TRANSACTION;

-- 1. Confirm current (broken) state before changing anything.
SELECT id, product_id, variation_id, quantity, quantity_sold, quantity_adjusted, quantity_returned
FROM purchase_lines
WHERE id = 3505 AND product_id = 116 AND variation_id = 708;

-- 2. Apply the correction.
UPDATE purchase_lines
SET quantity_sold = 2
WHERE id = 3505
  AND product_id = 116
  AND variation_id = 708
  AND quantity_sold = 3;   -- guard: only apply if it's still in the exact broken state diagnosed above

-- 3. Confirm exactly one row was updated.
SELECT ROW_COUNT() AS rows_updated;

-- 4. Re-check the row after the fix.
SELECT id, quantity, quantity_sold, quantity_adjusted, quantity_returned,
       (quantity - quantity_sold - quantity_adjusted - quantity_returned) AS remaining
FROM purchase_lines
WHERE id = 3505 AND product_id = 116 AND variation_id = 708;

-- If rows_updated = 1 and remaining = 1 above, run:
-- COMMIT;
--
-- If anything looks off (e.g. rows_updated = 0 because the data has moved on
-- since this diagnosis), run:
-- ROLLBACK;
