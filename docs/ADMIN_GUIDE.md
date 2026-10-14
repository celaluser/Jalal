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

## Store: selling themes and premium features
Open **Billing, Store catalog**. Every plan feature and every menu theme is listed. Click **Edit** to choose:
- **Free for everyone**: all restaurants can use it.
- **With plans only**: only plans that include it (set under Plans; themes use the "All premium themes" plan switch) or restaurants you give it to by hand. This is how features worked before the store.
- **For sale**: restaurants can buy it in their Store. Choose one time (for good), rented by the month or rented by the year, the price and currency, an optional free trial, and the name, short line and details restaurants see. Plans that include the item keep it free.

Payments use the gateways you set up for subscriptions, with the same tax settings. A paid invoice unlocks the item at once (bank transfer: when you mark the invoice paid). Rentals do not renew by themselves: restaurants extend them from the item page, and the item locks again when time runs out (a theme falls back to your default theme).

**Billing, Store sales** shows what restaurants own, store revenue per currency, and lets you give an item to a restaurant (for some months or for good) or take it back.

**Store coupons and bundles.** Under Billing, Coupons choose "Valid for": plans, store items or both, and optionally limit a coupon to chosen store items. Under Store catalog, "New bundle" sells several themes and features together for one price; restaurants see how much they save and every item inside unlocks for the same time. A reminder e-mail goes out a week before a rented item ends (change the text under E-mail templates).

## Simple menu and setup checklist
The admin menu shows the essentials (restaurants, plans, subscriptions, payments, settings, system status). Tap **Show all features** for everything else. The dashboard lists five setup steps (name and logo, a plan, a payment gateway, e-mail, the scheduler) and hides the list when all are done.

## Brand, SEO and Google (Settings)
- **Settings > General:** site name, tagline (shown with the name in the page title), logo, logo for dark backgrounds (optional; without it the normal logo is shown on a white tile), favicon and the share image (1200x630, used by Facebook, WhatsApp, X and LinkedIn previews). The logo appears in the site header and footer, sign-in screens, the panel menus and e-mails.
- **Settings > SEO:** default title and description, a switch to let search engines list the site (turn it off while building: robots.txt closes the site and every page says noindex), X/Twitter handle and extra robots.txt lines. `/robots.txt` and `/sitemap.xml` are generated for you; sign-in, panel and installer pages are never indexed.
- **Settings > Google:** Google Analytics (G-...) or Google Tag Manager (GTM-...), not both; Google Search Console, Bing, Yandex and Meta verification (paste the code or the whole meta tag); extra head code for other tools. Tracking starts only after a visitor accepts cookies. After saving, verify the site in Search Console and submit `https://your-site/sitemap.xml`.
- **Settings > Security:** reCAPTCHA v2 (checkbox) or v3 (invisible, you choose the lowest accepted score) or Cloudflare Turnstile; content security policy (try Report only first).
- **Speed:** System status lists whether PHP OPcache and the config/route cache are on; the Optimize button builds the caches. The package compresses text and caches versioned files for a year (Apache). Pages carry structured data (organization, website, software offers, blog articles) and canonical, language and share tags.
