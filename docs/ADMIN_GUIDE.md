# Platform administrator guide

You (the super admin) run the whole platform: restaurants, plans, billing and the public site.

## Daily overview

The **Dashboard** shows new restaurants, revenue and subscriptions. **System status** lists server checks (PHP extensions, writable folders, HTTPS, signing key, queue) and whether the scheduler and queue worker are alive. Both must show green for e-mails, reminders and webhooks to work.

## Restaurants

Open, suspend, or sign in as an owner to help. Suspended restaurants' menus show a polite "unavailable" page.

## Plans and subscriptions

- A plan has a price per interval (monthly, yearly or one-time lifetime), a trial, limits (branches, tables, products, categories, staff, AI credits, messages per month, online orders per month, menu views per month) and features (custom domain, WhatsApp orders, online payments, reservations, analytics, remove branding, REST API & webhooks). Empty limit = unlimited.
- Coupons, tax and invoicing settings, manual subscription assignment and renewal are under **Billing**.
- Subscription payments are taken through the gateways you enable (Stripe, PayPal, Paddle, Mollie, Razorpay, Paystack, Flutterwave, iyzico, PayTR, Epoint, Mercado Pago, Midtrans, bank transfer). Add the keys under **Payment gateways**, and the webhook URLs shown there in each gateway's dashboard.
- **Restaurant payments** controls whether restaurants may take online payments from guests with their own gateway accounts, and the optional platform commission.

## Content

Landing page sections, blog posts and free pages are edited under **Website**. Language packs and the translation editor are under **System → Languages / Translations**. Add a language, edit any text in the browser, and set right-to-left for Arabic/Hebrew.

## Messaging and AI

- **SMS & WhatsApp**: choose a provider per channel (Twilio, Vonage, MessageBird, WhatsApp Cloud API, Netgsm, İleti Merkezi). Keys are stored encrypted. Restaurants spend their plan's monthly message allowance.
- **AI**: choose OpenAI, Anthropic or Gemini (or a compatible endpoint) and enter the key. Each restaurant has monthly AI credits per plan; credits are charged only for usable answers.

## Maintenance

- **System status → Run without a terminal**: the secret cron address, the fallback mode and one-click server tools (update database, speed up, fix images, run scheduler/queue now, retry/delete failed jobs, back up).
- **Backups**: a database backup runs nightly (newest 7 kept); download or run one by hand.
- **Updates**: upload a signed zip (see `UPDATING.md`). A backup of the files it changes is taken first.
- **Add-ons**: install signed add-ons (see `ADDONS.md`).
- **Logs** shows the application log.
- Maintenance mode can be switched on from the System status page.

## Security checklist

- `APP_DEBUG=false`, `APP_ENV=production`, HTTPS on.
- `UPDATER_PUBLIC_KEY` set; never `UPDATER_ALLOW_UNSIGNED` in production.
- Two-factor sign-in on your admin account (Profile).
- Real queue worker (not `sync`) and the cron job running.
- Back up the `.env` file, the database and `storage/app/public`.
