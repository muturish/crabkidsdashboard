-- Bulk fix for "NOT ALLOWED: Mismatch between sold and purchase quantity" errors.
-- Generated from crabkidskenyaco_pos.sql (31 Aug 2026 dump).
--
-- Covers 31 SKU/location pairs where variation_location_details.qty_available
-- exceeds the remaining capacity across that SKU's purchase batches at that location.
-- For each, quantity_sold is reduced on the oldest batch(es) with sold > 0, just enough
-- to make remaining capacity match qty_available -- same fix pattern already applied to
-- purchase_line 3505 (P617648-S37-C) individually.
--
-- Each UPDATE is guarded by the exact current quantity_sold value diagnosed from the dump,
-- so if live data has moved on since 31 Aug 2026, that statement simply updates 0 rows
-- instead of corrupting anything -- check ROW_COUNT()/re-run diagnosis before trusting it.
--
-- ALWAYS take a fresh database backup before running this against production.

START TRANSACTION;

-- SKU P617421-S35-B  (P617421 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=7  purchase_remaining=3  deficit=4
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 1558 AND variation_id = 421 AND quantity_sold = 2;
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 2285 AND variation_id = 421 AND quantity_sold = 1;
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 2762 AND variation_id = 421 AND quantity_sold = 1;

-- SKU P617421-S31-B  (P617421 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=6  purchase_remaining=4  deficit=2
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 1554 AND variation_id = 417 AND quantity_sold = 2;

-- SKU P617656-S29-A  (P617656 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=2  purchase_remaining=0  deficit=2
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 1580 AND variation_id = 501 AND quantity_sold = 3;

-- SKU P617480-S32-B  (P617480 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=3  purchase_remaining=1  deficit=2
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 2028 AND variation_id = 667 AND quantity_sold = 2;

-- SKU P618303-S33-B  (P618303 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=5  purchase_remaining=3  deficit=2
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 2083 AND variation_id = 650 AND quantity_sold = 1;
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 2406 AND variation_id = 650 AND quantity_sold = 2;

-- SKU 617617-S27-A  (617617 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=9  purchase_remaining=7  deficit=2
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 3563 AND variation_id = 752 AND quantity_sold = 4;

-- SKU P618301-S27-A  (P618301 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=4  purchase_remaining=2  deficit=2
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 3740 AND variation_id = 608 AND quantity_sold = 1;
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 4069 AND variation_id = 608 AND quantity_sold = 2;

-- SKU P617617-S36-B  (P617617 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=5  purchase_remaining=4  deficit=1
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 315 AND variation_id = 12 AND quantity_sold = 1;

-- SKU P617634-S33-B  (P617634 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=2  purchase_remaining=1  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 373 AND variation_id = 225 AND quantity_sold = 3;

-- SKU P617501-S35-B  (P617501 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 3 WHERE id = 387 AND variation_id = 261 AND quantity_sold = 4;

-- SKU P617311-S31-B  (P617311 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 7 WHERE id = 1372 AND variation_id = 449 AND quantity_sold = 8;

-- SKU P617421-S30-A  (P617421 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 3 WHERE id = 1396 AND variation_id = 416 AND quantity_sold = 4;

-- SKU P617421-S32-B  (P617421 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 1555 AND variation_id = 418 AND quantity_sold = 2;

-- SKU P617421-S33-B  (P617421 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=4  purchase_remaining=3  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 1556 AND variation_id = 419 AND quantity_sold = 2;

-- SKU 617611-S34-B  (617611 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 7 WHERE id = 1223 AND variation_id = 56 AND quantity_sold = 8;

-- SKU P617656-S33-B  (P617656 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=3  purchase_remaining=2  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 1240 AND variation_id = 505 AND quantity_sold = 3;

-- SKU 617353-S30-A  (617353 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 1791 AND variation_id = 557 AND quantity_sold = 3;

-- SKU P617658-S34-B  (P617658 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 1841 AND variation_id = 597 AND quantity_sold = 2;

-- SKU P617628-S27-A  (P617628 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 1867 AND variation_id = 626 AND quantity_sold = 3;

-- SKU P617303-S39-C  (P617303 Size 37-42)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 2055 AND variation_id = 44 AND quantity_sold = 2;

-- SKU P617303-S41-C  (P617303 Size 37-42)  @ CrabKids Kenya Star Mall Shop
-- qty_available=6  purchase_remaining=5  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 2057 AND variation_id = 46 AND quantity_sold = 2;

-- SKU P617520-S36-B  (P617520 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 2253 AND variation_id = 689 AND quantity_sold = 3;

-- SKU P617632-S38-C  (P617632 Size 37-42)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 3406 AND variation_id = 93 AND quantity_sold = 2;

-- SKU P617311-S25-A  (P617311 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=2  purchase_remaining=1  deficit=1
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 3455 AND variation_id = 443 AND quantity_sold = 1;

-- SKU P617648-S37-C  (P617648 Size 37-42)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 3505 AND variation_id = 708 AND quantity_sold = 3;

-- SKU P617648-S39-C  (P617648 Size 37-42)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 3507 AND variation_id = 710 AND quantity_sold = 3;

-- SKU 617617-S29-A  (617617 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=6  purchase_remaining=5  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 3565 AND variation_id = 754 AND quantity_sold = 2;

-- SKU P618106-S26-A  (P618106 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=6  purchase_remaining=5  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 3618 AND variation_id = 769 AND quantity_sold = 2;

-- SKU P617301-S39-C  (P617301 Size 37-42)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 2 WHERE id = 3705 AND variation_id = 354 AND quantity_sold = 3;

-- SKU P618301-S25-A  (P618301 Size 25-30)  @ CrabKids Kenya Star Mall Shop
-- qty_available=10  purchase_remaining=9  deficit=1
UPDATE purchase_lines SET quantity_sold = 0 WHERE id = 3738 AND variation_id = 606 AND quantity_sold = 1;

-- SKU 617301-S32-B  (617301 Size 31-36)  @ CrabKids Kenya Star Mall Shop
-- qty_available=1  purchase_remaining=0  deficit=1
UPDATE purchase_lines SET quantity_sold = 1 WHERE id = 3844 AND variation_id = 883 AND quantity_sold = 2;

-- Review the affected rows below. If everything matches (each variation/location now has
-- remaining capacity == qty_available), run COMMIT; otherwise run ROLLBACK;
SELECT id, product_id, variation_id, quantity, quantity_sold, quantity_adjusted, quantity_returned
FROM purchase_lines
WHERE id IN (1558, 2285, 2762, 1554, 1580, 2028, 2083, 2406, 3563, 3740, 4069, 315, 373, 387, 1372, 1396, 1555, 1556, 1223, 1240, 1791, 1841, 1867, 2055, 2057, 2253, 3406, 3455, 3505, 3507, 3565, 3618, 3705, 3738, 3844);

-- COMMIT;
-- ROLLBACK;