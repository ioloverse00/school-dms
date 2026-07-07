# Deploying this Laravel app to `server10.indevfinite-server.com`

This repo is a **Laravel 12 (PHP 8.2+)** app using **Vite** (built assets live in `public/build`).

Because “Indevfinite Server” can be either shared hosting (File Manager + optional SSH) or a VPS, this guide covers both common layouts:

- **Layout A (Best):** web document root points to the app’s `public/` directory.
- **Layout B (Fallback):** web root stays as-is (e.g. `public_html/`), so you copy `public/` contents into the web root and adjust `index.php`.

## 1) Confirm the hosting type / access

You need at least one of:

- **File Manager / SFTP** access (manual upload), and/or
- **SSH** access (to run `php artisan` commands and migrations).

If you do not have SSH, you can still upload the app, but migrations and caching commands become harder.

## 2) Build a deployable zip (recommended)

On your machine (repo root), run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/indevfinite/make-deploy-zip.ps1
```

This writes `dist/indevfinite-deploy.zip` and excludes `node_modules/`, `.env`, `tests/`, and `public/hot`.

Notes:

- If your server cannot run Composer, keep `vendor/` included (default).
- If your server cannot run Node builds, keep `public/build/` included (default).

## 3) Upload + extract on the server

Upload `dist/indevfinite-deploy.zip` to a folder outside the web root if possible (example: `~/apps/school-dms/`), then extract it there.

## 4) Choose Layout A vs Layout B

### Layout A (Best): set document root to `public/`

If your control panel lets you set the domain document root, point it to:

`.../school-dms/public`

Then the rest of the Laravel project sits one level above `public/`.

### Layout B (Fallback): keep the existing web root

If the web root must remain something like `public_html/`:

1. Put the Laravel project outside the web root (example: `~/laravel-app/`).
2. Copy everything from `~/laravel-app/public/` into `public_html/`.
3. Edit `public_html/index.php` so the paths point to the app folder, for example:

```php
require __DIR__.'/../laravel-app/vendor/autoload.php';
$app = require_once __DIR__.'/../laravel-app/bootstrap/app.php';
```

Also copy `~/laravel-app/public/.htaccess` into `public_html/.htaccess`.

## 5) Create `.env` (production)

Create a production `.env` on the server (never place it in the web root).

Minimum checklist:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://server10.indevfinite-server.com` (or your real domain if different)
- `APP_KEY=base64:...`
- Database values: `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

## 6) Run Laravel commands (SSH)

From the app folder:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you do not have SSH:

- Generate the key locally with `php artisan key:generate --show` and paste it into the server `.env`.
- For migrations, either enable SSH, use a VPS, or run migrations from a trusted CI job.

## 7) Permissions

Ensure these are writable by PHP:

- `storage/`
- `bootstrap/cache/`

## 8) Vite assets

Production assets must exist in `public/build/`.

Rebuild locally when frontend changes:

```bash
npm ci
npm run build
```

## Quick “is it working?” checklist

- Visiting `https://server10.indevfinite-server.com/` loads the app (no 500 error)
- DB connects (DB-backed pages load)
- `storage/` is writable (logging/uploads work)
- `APP_DEBUG=false` in production

