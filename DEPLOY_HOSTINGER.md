# Deploying this Laravel app to Hostinger

This repo is a **Laravel 12 (PHP 8.2+)** app using **Vite** (built assets live in `public/build`).

## 0) Choose your Hostinger type

- **Shared Hosting (hPanel / `public_html`)**: most common; limited server access; SSH may or may not be enabled.
- **VPS**: you control Nginx/Apache + Supervisor; simplest “proper” Laravel deployment.

The steps below cover **Shared Hosting** first, then quick VPS notes.

---

## 0.5) Set PHP version (Shared Hosting)

In hPanel, set the site to **PHP 8.2+**.

Common required extensions: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`.

---

## 1) Create the database (Shared Hosting)

1. hPanel → **Databases** → **MySQL Databases**
2. Create:
   - a database (example: `school_dms`)
   - a user + password
3. Note your:
   - DB host (often `localhost` on shared hosting)
   - DB name, username, password

---

## 2) Upload the project

You have two workable layouts on shared hosting:

### What to upload

- Upload `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, and `artisan`.
- Also upload `vendor/` **and** `public/build/` if you cannot run Composer / Vite build on the server.
- Do **not** upload `node_modules/`.
- Do **not** upload `public/hot` (it forces Vite dev mode and will break assets in production).
- Never upload a real `.env` into a public folder; create it on the server.

Optional: create an upload zip on Windows with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/hostinger/make-deploy-zip.ps1
```

This writes `dist/hostinger-deploy.zip` (it auto-excludes `.env`, `node_modules/`, and `public/hot`).

### Option A (Best): Set domain document root to `public/`

If Hostinger lets you change the domain **Document Root**, point it to:

`.../your-app/public`

Then upload the Laravel app to `your-app/` (including `public/`).

### Option B (Fallback): Keep `public_html` as the web root

If you cannot change the document root:

1. Upload the Laravel app **outside** `public_html` (example: `laravel-app/`).
2. Copy contents of `laravel-app/public/` into `public_html/`.
3. Edit `public_html/index.php` so paths point to the app folder, for example:

```php
require __DIR__.'/../laravel-app/vendor/autoload.php';
$app = require_once __DIR__.'/../laravel-app/bootstrap/app.php';
```

Also copy `laravel-app/public/.htaccess` into `public_html/.htaccess`.

---

## 3) Configure `.env` on the server

Create a production `.env` on the server.

Minimum checklist:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=base64:...` (generate on-server with `php artisan key:generate --force`, or locally with `php artisan key:generate --show`)
- `APP_URL=https://your-domain.com`
- Database values: `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

If you use email, also set `MAIL_*`.

---

## 4) Run Laravel commands (SSH/Terminal if available)

From the app folder:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you do not have SSH on your plan:

- Generate `APP_KEY` locally via `php artisan key:generate --show` and paste it into the server `.env`.
- You still need a way to run migrations (enable SSH, use a VPS, or run migrations from CI). Creating tables manually is possible but not recommended.

---

## 5) Permissions (Shared Hosting)

Ensure these are writable by PHP:

- `storage/`
- `bootstrap/cache/`

Typical permissions are `775` for those folders.

---

## 6) Cron jobs (optional but common)

If you use Laravel Scheduler:

- hPanel → **Advanced** → **Cron Jobs**
- Run every minute:

```bash
php /path/to/artisan schedule:run
```

If you use queues on shared hosting, consider `QUEUE_CONNECTION=sync` unless you have a persistent worker process.

---

## 7) Vite assets

Built assets should exist in `public/build/`.

If you change frontend code later, rebuild locally and re-upload:

```bash
npm ci
npm run build
```

---

## 8) Note about `windmill-dashboard/public/*`

Hostinger will only serve files inside your web root (typically `public/` or `public_html/`).

So `windmill-dashboard/public/tables.html` will **not** be reachable unless you copy it into:

- `public/tables.html` (Option A), or
- `public_html/tables.html` (Option B)

---

## Quick “is it working?” checklist

- Visiting your domain loads the Laravel app (no 500 error)
- `storage/` is writable (logging/uploads work)
- DB connects (pages that query DB load)
- `APP_DEBUG=false` in production
