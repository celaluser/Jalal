# Developer guide

Laravel 12, PHP 8.2+, Blade, Livewire 3 (it brings Alpine.js), Tailwind 4 (compiled with Vite and shipped in `public/build`).

## Structure: a modular monolith

Everything lives in `app/Modules/<Name>`: `Providers`, `Http/Controllers`, `Models`, `Services`, `Resources/views` (view namespace = module name in lowercase), `Database/Migrations`, `routes.php`. Providers are listed in `bootstrap/providers.php`.

Main modules: Core (helpers, mail, tenancy context, settings), Tenancy, Auth, Billing, Admin, Menu, Tables, Orders, Storefront (guest pages), Marketing, Reservations, Branches, Analytics, Ai, Messaging, Api, Addons, Updater, Licensing, Installer, Cms, Support, Team, Affiliate, Activity, Demo.

## Multi-tenancy

- A restaurant is the tenant. Models use the `BelongsToRestaurant` trait: a global scope filters by the current tenant and **fails closed** (no tenant = no rows).
- `TenantContext::runAs($restaurant, fn)` for jobs, commands and tests. Middleware `ResolveTenant` (guest routes) and `SetTenantFromUser` (panel) set it for requests.
- Permissions use spatie/laravel-permission with one team per restaurant. Roles and defaults are in `Auth/Support/Permissions.php`.
- Plan limits and features: `LimitGuard` (`hasFeature`, `limit`). Features are listed in `Plan::FEATURES`.

## Extension points

`RestaurantNav` / `AdminNav` (menus), `EmailTemplateRegistry` (editable e-mails), `UsageRegistry` (plan usage), `DemoDataRegistry`, Laravel events (`OrderPlaced`, `OrderStatusChanged`, `OrderPaid`, ...), `ThemeRegistry` (config/themes.php), payment gateways (`Billing/Gateways`), AI providers, messaging providers. Add-ons plug into these (see `ADDONS.md`).

## Conventions

- All user-facing text comes from `lang/<locale>/*.php`; English is complete. The guest-facing `customer.php` must have identical keys in every shipped language (a test enforces it).
- Money is stored in minor units (cents). Prices on the guest side are always re-priced on the server (`CartPricing`); the browser's cart is never trusted.
- Secrets are never hard-coded: admin settings (encrypted) or `.env`.
- Public endpoints are rate limited; webhooks are signed and verified.

## Tests

`php artisan test` (Pest). Feature tests per module in `tests/Feature`. Gateway, SMS, AI and webhook calls are tested against faked HTTP responses only.

Pitfalls: with the sync queue, jobs run immediately; tenant-scoped relations return nothing outside a tenant context; `Http::fake` accumulates across calls in one test (use `Http::swap(new Factory)`); helper function names in Pest files are global, so prefix them per file.

## Building assets

`npm ci && npm run build`. The compiled files in `public/build` are committed/shipped so customers need no Node.

## Building the manual

`php artisan docs:licenses` then `php artisan docs:build` writes `docs/html/index.html`.
