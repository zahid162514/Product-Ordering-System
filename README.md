# SmartStock Product Ordering

SmartStock is a PHP/MySQL product ordering and inventory management system for Laobaan Bangladesh LTD.

## Current Features

- Customer registration, login, logout, profile editing, and token-based password reset.
- Product catalog with search, category filtering, stock visibility, cart, checkout, coupons, and reorder from past orders.
- Checkout with delivery details, delivery fee, payment method, optional receipt upload, stock-safe transactions, and customer email notifications.
- Grouped orders with order items, order status history, payment tracking, courier/tracking details, PDF invoice download, and printable invoice view.
- Admin dashboard, product/category CRUD, product SKU fields, reorder levels, product variants, coupons, and inventory adjustments.
- Inventory ledger for checkout, cancellation, manual adjustment, restock, and damaged stock events.
- Support workflow with assignment, status, notes, reply summaries, and resolution tracking.
- CSV exports for products, orders, customers, inventory ledger, and support messages.
- Role-aware admin access: `super_admin`, `manager`, `inventory`, and `support`.
- CSRF protection, hardened sessions, password hashing, and prepared statements across core workflows.

## Requirements

- PHP 7.4 or newer
- MySQL 5.7 or newer
- Apache/XAMPP or PHP built-in server

## Database Setup

Create the database and import `sql schema.sql` for a fresh install.

The fresh schema includes a small demo seed so you can start quickly:

- Admin login: `admin` / `Admin@12345`
- Customer login: `customer@smartstock.local` / `Customer@12345`
- Demo coupon: `WELCOME10`

Please change the demo credentials before using the app in any real environment.

For an existing database that was created before grouped orders and business workflow features were added, run these migrations in order:

1. `migrations/2026_06_04_create_grouped_orders.sql`
2. `migrations/2026_06_06_business_features.sql`
3. `migrations/2026_06_07_sslcommerz_payments.sql`

The migration files are written to be rerunnable on MySQL 5.7+ and should not be run after importing the full fresh-install schema from `sql schema.sql`.

Database credentials are read from environment variables in `includes/config.php`, with local XAMPP defaults:

```php
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=products_ordering_db
DB_PORT=3306
```

## Local Run

With XAMPP, place this folder under `htdocs` and open:

```text
http://localhost/PRODUCTS_ORDERING/
```

Or use the PHP built-in server:

```bash
php -S localhost:8000
```

## Admin Roles

- `super_admin`: all access, including admin user management.
- `manager`: products, categories, orders, coupons, exports, support, and inventory workflows.
- `inventory`: products, categories, inventory, variants, and stock ledger workflows.
- `support`: support inbox and customer exports.

After migration, the first existing admin is promoted to `super_admin`.

## Operational Notes

- Keep `APP_ENV=production` in production. `setup.php` and `import.php` are environment-gated and should remain disabled publicly.
- Configure local mail or SMTP forwarding if password reset, order notification, and low-stock alert emails need to leave the machine.
- See `PAYMENT_AND_EMAIL_SETUP.md` for SMTP and SSLCOMMERZ sandbox environment variables.
- Uploaded product images, receipts, and other files are stored in `uploads/`.
