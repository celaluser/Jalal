# Marketplace listing material

Everything here is written to be accurate for this code base; adjust prices and support terms to your own.

## Title

QR Menu & Online Ordering SaaS – Multi-Restaurant Platform with Subscriptions

## Short description

Launch your own QR menu and online ordering platform. Restaurants get a menu, orders, kitchen display, reservations, marketing and reports; you earn from subscription plans. Installs on shared hosting.

## Description

**Run a platform where restaurants sign up, publish a QR menu and take orders. You sell the subscriptions.**

*For you (platform owner)*
- Web installer, no command line needed. Works on shared hosting (cron + database queue); Node.js is not needed on the server.
- Plans with trials, one-time lifetime plans, limits and feature switches, coupons, tax and invoices, affiliate programme.
- Subscription payments: Stripe, PayPal, Paddle, Mollie, Razorpay, Paystack, Flutterwave, iyzico, Mercado Pago, Midtrans, bank transfer.
- Your landing page, blog and pages, edited in the browser. Languages and translations editable online (English complete; Turkish and Arabic for guest texts), right-to-left support.
- Custom domains and subdomains for restaurants (VPS or DNS access needed), white-label option per plan.
- Signed one-click updates, add-ons, backups, system status, activity log, two-factor sign-in.
- REST API and signed webhooks.

*For restaurants*
- Menu with sizes, extras, combos, allergens, schedules, multi-language, bulk CSV import, branches.
- QR codes (sheets, table tents, NFC), installable guest app, themes, light/dark.
- Online ordering for dine-in, takeaway, delivery, curbside, room service; kitchen display, POS, split bills, tips, shifts, ticket printing.
- Reservations, reviews and NPS, promo codes, loyalty, gift cards, happy hour, campaigns by e-mail/SMS/WhatsApp, banners, About page and SEO, link-in-bio.
- Reports, menu engineering, weekly summary e-mail.
- AI helpers (menu import from photo/PDF, translations, review replies, sales tips, guest assistant) using your own OpenAI/Anthropic/Gemini key.

## Requirements

PHP 8.2+, MySQL 5.7+/MariaDB 10.3+ or SQLite, PHP extensions listed in the installation guide, HTTPS, a cron job.

## What is not included (say this in the listing)

- Third-party accounts: payment gateways, SMS/WhatsApp, AI providers and mail servers need your own accounts and keys. The integrations were tested against simulated responses, not live accounts.
- Dish pictures in the demo are illustrations, not photographs.
- Legal texts (privacy policy, terms) are yours to write.
- Online refunds are automated for Stripe only. Receipt printing uses ESC/POS and does not print Arabic on most printers.
- No native mobile apps; the guest and staff apps are installable web apps.

## Support policy (example)

Six months of support for installation problems and bugs, through the item's comments/support channel. Customisations and third-party account setup are not covered.

## Demo

Run with `DEMO_MODE=true` and `php artisan db:seed --class=DemoSeeder`; data is restored every night. Demo logins are shown on the login page.

## Changelog

See `CHANGELOG.md`.
