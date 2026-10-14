# QR Menu & Online Ordering SaaS

A multi-tenant platform where restaurants publish a QR menu, take orders and reservations, and market to their guests, and where **you** sell subscriptions to those restaurants.

Laravel 12 · PHP 8.2+ · Blade + Livewire 3 + Tailwind 4 · MySQL/MariaDB or SQLite · runs on shared hosting (database queue, cron, no Node.js on the server).

## Three levels of users

- **Super admin**: restaurants, plans and subscriptions (11 payment gateways), landing page and blog, languages, e-mail templates, SMS/WhatsApp and AI providers, updates, add-ons, backups.
- **Restaurant**: menu, QR codes, orders (kitchen display, POS), payments, reservations, customers and marketing, reports, team and branches, REST API.
- **Guest**: scans a QR code, sees the menu in their language, orders and pays, follows the order, books a table, leaves a review.

## Documentation

Open `docs/html/index.html` for the manual (or read the Markdown in `docs/`):
[Installation](docs/INSTALLATION.md) · [Owner guide](docs/USER_GUIDE.md) · [Admin guide](docs/ADMIN_GUIDE.md) · [Updating](docs/UPDATING.md) · [API](docs/API.md) · [Add-ons](docs/ADDONS.md) · [Developer guide](docs/DEVELOPER.md) · [Troubleshooting](docs/TROUBLESHOOTING.md) · [Feature status](docs/FEATURE_STATUS.md) · [Licences](docs/THIRD_PARTY_LICENSES.md)

## Quick start (development)

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run dev      # or: npm run build
php artisan serve
php artisan app:create-super-admin
```

Tests: `php artisan test`.

## Honest status

`docs/FEATURE_STATUS.md` lists what is done, partial and not built. Payment gateways, SMS/WhatsApp providers, AI providers, web push and printers are tested against simulated responses and byte-level checks, not against live accounts; try each integration you plan to sell with your own test credentials before launch.
