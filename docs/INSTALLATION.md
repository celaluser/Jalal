# Installation

## Requirements

- PHP 8.2 or newer (tested on 8.2 compatible dependency versions; 8.3 and 8.4 also work) with these extensions: ctype, curl, dom, fileinfo, gd (with WebP), intl, json, mbstring, openssl, pdo, tokenizer, xml, zip (and sodium for signed updates)
- MySQL 5.7+ / MariaDB 10.3+, or SQLite for small sites and trials
- Apache or Nginx pointing at the `public/` folder
- A cron job (every minute) so scheduled tasks run, and a queue worker (or the cron fallback below) for e-mails, webhooks and background jobs
- HTTPS for production (required for the installable app, push notifications and payments)

No Node.js or Composer is needed on the server: `vendor/` and the compiled assets (`public/build`) are included.

## Shared hosting (cPanel and similar)

1. Upload the zip and extract it **above** the web root if you can, for example `~/qrmenu/`. Point the domain's document root at `~/qrmenu/public`.
   If you cannot change the document root, extract into a folder and ask your host to point the domain to its `public` subfolder.
   **If the whole package ended up inside `public_html`** (no way to change the document root), it still works: the `.htaccess` and `index.php` in the package root send every request to `public/` and close the private folders (`app`, `vendor`, `storage`, `.env` and so on). Keep both files, and check that `https://your-site/.env` shows "Forbidden" or "Not found". Without that `.htaccess` the site shows "Forbidden" and your `.env` could be downloaded.
2. Create an empty MySQL database and user (or choose SQLite in the installer).
3. Open `https://your-domain/install` and follow the steps: requirements check, licence, database, administrator account.
4. Add the cron job (cPanel → Cron Jobs, every minute). **No terminal or `php` path? Use the web address instead**: the last installer page (and *Admin → System status*) shows `https://your-domain/cron/<secret>`; call it every minute with `wget -q -O /dev/null <address>` or any free cron service. It runs the scheduler and the queue inside the web request. With neither a cron job nor the web address, the script falls back to doing the work after visitors' page loads (Admin → System status → fallback mode).

   Classic cron line:

   ```
   * * * * * /usr/local/bin/php /home/USER/qrmenu/artisan schedule:run >> /dev/null 2>&1
   ```

5. Background jobs. Best: a queue worker run by your host (Supervisor on a VPS, "long-running process" on some hosts):

   ```
   php artisan queue:work --sleep=3 --tries=3 --max-time=3600
   ```

   Without a worker on shared hosting, add a second cron job that processes the queue for a minute and exits:

   ```
   * * * * * /usr/local/bin/php /home/USER/qrmenu/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
   ```

6. Sign in at `/login`, open **Admin → Settings** and fill in the mail (SMTP) settings, then send a test e-mail.

## VPS (Ubuntu example)

```
sudo apt install nginx php8.3-fpm php8.3-{mysql,mbstring,xml,curl,gd,intl,zip} mariadb-server supervisor
```

- Nginx: `root /var/www/qrmenu/public;` with the standard Laravel `try_files $uri $uri/ /index.php?$query_string;`
- File permissions: the web user must write `storage/` and `bootstrap/cache/`.
- Supervisor program for `php artisan queue:work`, and the one-minute cron for `schedule:run`.
- Subdomains per restaurant (`pizza.example.com`) need wildcard DNS and a wildcard certificate; set `TENANCY_BASE_DOMAIN` and `TENANCY_SUBDOMAINS=true` in `.env`. Restaurants' own domains need each domain pointed at the server (see the Domains page in the restaurant panel). The address `https://your-domain/r/restaurant-slug` always works without any DNS work.

## Running everything from the browser

You never need a terminal: the installer creates the database and the storage link, **Admin → System status** has buttons for *Update database*, *Speed up*, *Fix uploaded images*, *Run scheduled tasks now*, *Process waiting jobs now*, *Retry/Delete failed jobs* and *Back up database now*, and **Admin → Updates** applies updates. If your host forbids symlinks, uploaded images are served by the script itself.

## After installing

| Task | Where |
| --- | --- |
| Mail (SMTP) | Admin → Settings → Mail |
| Plans and prices | Admin → Plans |
| Payment gateways for subscriptions | Admin → Payment gateways |
| SMS / WhatsApp providers | Admin → SMS & WhatsApp |
| AI provider and keys | Admin → Settings → AI |
| Languages and currencies | Admin → Languages / Currencies |
| Landing page, blog, pages | Admin → Landing page / Blog / Pages |
| Backups | Admin → Backups (a nightly database backup also runs by itself) |

Keep secrets (API keys, SMTP password) in the admin settings or `.env`. Never commit `.env`.

## Signed updates

Updates are uploaded in **Admin → Updates** as signed zip files. Put the vendor's public key in `.env` as `UPDATER_PUBLIC_KEY=...`; without it, updates and add-ons are refused. See `UPDATING.md`.

## Demo mode

Setting `DEMO_MODE=true` makes a public demo: destructive changes are blocked and data is restored every night. Do not use it on a real site.

## Uninstalling or reinstalling

Delete `storage/app/installed` to run the installer again (it will ask for a database; existing tables are not removed for you).
