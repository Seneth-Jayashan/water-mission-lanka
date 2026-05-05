PHP Admin Backend

Simple Pure PHP backend for admin product management.

Setup:

1. Create a MySQL database (e.g. `water_mission`).
2. Edit `config.php` and set your DB credentials.
3. Import the SQL schema: `backend/init.sql` into your database, or run `setup_admin.php` which will create tables if missing.
4. Run `backend/setup_admin.php` in the browser or CLI to create the initial admin user.

Files:
- `public/` — public admin pages (login, dashboard, products, categories)
- `config.php` — DB connection
- `functions.php` — auth and helpers
- `init.sql` — DB schema
- `setup_admin.php` — create initial admin user

Product management features:
- Multi-image uploads per product (JPG/PNG/WEBP, max 2MB each)
- Many-to-many categories per product
- Product filtering (search/category/price/sort)
- CSV export: `public/products_export.php`
- CSV import: `public/products_import.php`

Security notes:
- Pages under `public/` call `requireAuth()` to block unauthorized access.
- Passwords use `password_hash()` and `password_verify()`.
