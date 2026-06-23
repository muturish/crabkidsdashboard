# CrabKids Stock Dashboard

A standalone PHP + Bootstrap dashboard that reads directly from your existing
UltimatePOS database (`crabkidskenyaco_pos`) to show stock growth, inventory
value, and low-stock alerts. It lives in its own project folder and never
writes to your POS database — every query is read-only.

## Pages

- **Overview** (`index.php`) — KPI cards, a stock-growth trend chart, stock
  by category, and a quick low-stock/out-of-stock summary.
- **Stock Growth** (`stock-growth.php`) — daily units received vs. sold,
  cumulative net change over a date range, and your most-restocked products.
- **Low Stock Alerts** (`low-stock.php`) — every variation at or below its
  configured alert quantity, plus a full out-of-stock list.

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension enabled
- Read access to your MySQL/MariaDB `crabkidskenyaco_pos` database
- Any standard web server (Apache, Nginx, or even `php -S` for local testing)

## Setup

1. **Copy the project** to its own folder on your server — separate from
   your main POS install, e.g. `/var/www/stock-dashboard/`.

2. **Create your `.env` file**:
   ```bash
   cp .env.example .env
   ```
   Then edit `.env` with your real database credentials:
   ```
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=crabkidskenyaco_pos
   DB_USER=your_db_username
   DB_PASS=your_db_password
   BUSINESS_ID=1
   ```
   `BUSINESS_ID` should match the business you want to report on — it's `1`
   for CrabKids in your current data.

3. **(Optional) Protect the dashboard** with a simple shared password by
   setting `DASHBOARD_PASSWORD` in `.env`. Leave it blank to skip login
   entirely (e.g. if the server itself is already access-restricted).

4. **Create a database user with read-only access**, if you haven't
   already. This is the safest way to connect — even if a query were ever
   wrong, it couldn't modify your POS data:
   ```sql
   CREATE USER 'dashboard_reader'@'localhost' IDENTIFIED BY 'a-strong-password';
   GRANT SELECT ON crabkidskenyaco_pos.* TO 'dashboard_reader'@'localhost';
   FLUSH PRIVILEGES;
   ```
   Use these credentials in your `.env` instead of your main app's DB user.

5. **Point your web server at the project folder** (or just open
   `index.php` directly if PHP's built-in server is fine for now):
   ```bash
   php -S localhost:8000
   ```
   Then visit `http://localhost:8000/`.

6. Turn off `display_errors` in `bootstrap.php` once you've confirmed
   everything works — it's left on by default to make initial setup easier.

## How "stock growth" is calculated

- **Units received** — summed from `purchase_lines` joined to `transactions`
  where `type = 'purchase'` and `status = 'received'` (i.e. confirmed stock
  that has actually arrived, not pending purchase orders).
- **Units sold** — summed from `transaction_sell_lines` joined to
  `transactions` where `type = 'sell'` and `status = 'final'`, net of any
  `quantity_returned`.
- **Manual adjustments** — summed from `stock_adjustment_lines` (can be
  positive or negative — covers damage write-offs, recounts, etc.).
- **Net stock change** = received − sold ± adjustments, shown both per-day
  and as a running cumulative total over your selected date range.
- **Current stock on hand** is a live snapshot from
  `variation_location_details.qty_available`, summed across all locations.

## Extending this project

This folder is meant to grow. A few natural next additions:

- A **Sales** page (revenue, best sellers, sales by location)
- A **location filter** dropdown (the `get_locations()` helper in
  `includes/data.php` is already there for this)
- Export-to-CSV buttons on the data tables
- A scheduled email digest of low-stock items (cron + PHP mail/SMTP)

All shared queries live in `includes/data.php` — add new functions there
and call them from a new page following the same pattern as the existing
three.

## Project structure

```
dashboard-project/
├── .env.example          # template for DB credentials — copy to .env
├── .gitignore
├── bootstrap.php          # shared init: error display, auth, DB connection
├── index.php              # Overview page
├── stock-growth.php       # Stock Growth page
├── low-stock.php          # Low Stock Alerts page
├── config/
│   ├── env.php            # tiny .env file loader
│   └── database.php       # PDO connection + business_id() helper
├── includes/
│   ├── auth.php           # optional shared-password gate
│   ├── login.php          # login form shown when DASHBOARD_PASSWORD is set
│   ├── data.php           # all SQL queries live here
│   ├── header.php         # shared layout: sidebar + page head
│   ├── footer.php         # shared layout: closing tags + scripts
│   └── date-filter.php    # reusable date-range picker
└── assets/
    └── css/
        └── dashboard.css   # custom theme on top of Bootstrap 5
```
