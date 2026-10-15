-- Fix: unblock sale of P617629-S31-B (variation_id=467) at Star Mall (location_id=1).
--
-- Diagnosis: at Star Mall, every purchase batch for this SKU shows 0 remaining
-- capacity (quantity - quantity_sold - quantity_adjusted - quantity_returned = 0
-- for all 6 batches), yet variation_location_details shows 1 unit physically
-- available there. No specific return/adjustment/transfer event in the data
-- explains the extra unit, so this frees up 1 unit of capacity on the oldest
-- batch (id=1082, the first-received batch at this location) rather than
-- fabricating a purchase history that isn't backed by evidence.
--
-- Current state of batch 1082: quantity=12, quantity_sold=12, remaining=0
-- After fix: quantity_sold=11, remaining=1 (matches the 1 physically available unit)
--
-- Run this ALONE (no other statements) so phpMyAdmin's default autocommit
-- applies it immediately -- do not wrap it in START TRANSACTION.

UPDATE purchase_lines
SET quantity_sold = 11
WHERE id = 1082
  AND product_id = 76
  AND variation_id = 467
  AND quantity_sold = 12;

-- Then run this separately to confirm it actually changed:
-- SELECT id, quantity, quantity_sold, quantity_adjusted, quantity_returned
-- FROM purchase_lines WHERE id = 1082;
