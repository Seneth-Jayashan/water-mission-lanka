PHP Admin Backend

Simple Pure PHP backend for admin product management.

Setup:

1. Create a MySQL database (e.g. `water_mission`).
2. Edit `config.php` and set your DB credentials.
3. Set `ADMIN_USERNAME` and `ADMIN_PASSWORD` as environment variables for the web server or CLI.
4. Import the SQL schema: `backend/init.sql` into your database, or run `setup_admin.php` which will create tables if missing.
5. Run `backend/setup_admin.php` in the browser or CLI to create the initial admin user.

Files:
- `public/` — public admin pages (login, dashboard, products, categories)
- `config.php` — DB connection
- `config.php` — DB connection and admin credential env lookup
- `functions.php` — auth and helpers
- `init.sql` — DB schema
- `setup_admin.php` — create initial admin user

Example environment file:
- `backend/.env.example`

Admin credential variables:
- `ADMIN_USERNAME`
- `ADMIN_PASSWORD`

How to set them:
- Windows PowerShell for the current session:
	```powershell
	$env:ADMIN_USERNAME = "admin"
	$env:ADMIN_PASSWORD = "change-me"
	php -S localhost:8000 -t backend
	```
- Apache virtual host or `.htaccess`:
	```apache
	SetEnv ADMIN_USERNAME admin
	SetEnv ADMIN_PASSWORD change-me
	```
- Nginx with PHP-FPM pool config:
	```ini
	env[ADMIN_USERNAME] = admin
	env[ADMIN_PASSWORD] = change-me
	```

Product management features:
- Multi-image uploads per product (JPG/PNG/WEBP, max 2MB each)
- Many-to-many categories per product
- Product filtering (search/category/price/sort)
- CSV export: `public/products_export.php`
- CSV import: `public/products_import.php`

Security notes:
- Pages under `public/` call `requireAuth()` to block unauthorized access.
- Passwords use `password_hash()` and `password_verify()`.
