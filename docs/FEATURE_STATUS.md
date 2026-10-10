# Feature status

What the script does today, what it does not do yet, and where the known gaps are.
Legend: ✅ built and tested · 🟡 built, with a stated limit · ⬜ not built.

Counts at the time of writing: 16 modules, 211 routes, 548 automated tests (all passing).

## 1. Platform foundation (Phase 1)
- ✅ Multi-tenant core: one database, every tenant row scoped by restaurant, fail-closed when no tenant is known, cross-tenant isolation tested on every route
- ✅ Tenant resolution by custom domain, subdomain or `/r/{slug}`
- ✅ Roles and permissions (super admin, owner, manager, waiter, kitchen, cashier) with per-restaurant teams; custom roles are managed by owners (Team module)
- ✅ Sign-in, registration (creates restaurant + owner), e-mail verification, password reset, 2FA (authenticator app), Google sign-in (admin toggle), reCAPTCHA, rate limits
- ✅ Settings engine (encrypted secrets), file uploads converted to WebP, translation loader with database overrides
- 🟡 Languages: English complete; Turkish and Arabic cover sign-in, installer, the **whole guest side** (menu, checkout, order tracking, allergen/diet labels) and the new Team/Domains screens' errors; the owner panel falls back to English

## 2. Installer, licence, updates, demo (Phase 2)
- ✅ Web installer (requirements check, database test, admin account, wipe-on-failure, lock file), SQLite and MySQL paths
- ✅ Licence check: Envato purchase code, own licence server, or format-only; unreachable server never blocks
- ✅ Signed updates (Ed25519), hash/path/symlink checks, backup, maintenance mode, rollback
- ✅ Demo mode: blocked write actions, nightly reset, three demo restaurants with menus, tables, brand colours and an active plan
- 🟡 Not exercised against a live MySQL install or the live Envato API

## 3. Super admin panel (Phase 3)
- ✅ Dashboard with KPIs and charts; restaurants (search, suspend, restore, impersonate); plans, subscriptions, invoices (PDF, tax), coupons
- ✅ 8 payment gateway drivers: Stripe, PayPal, Razorpay, Paystack, Flutterwave, Mollie, iyzico, bank transfer (signature checks, idempotent processing)
- ✅ Settings: general (name, logo, favicon, **primary and accent colour**), SEO/analytics, security, sign-in, SMTP, domains, AI, real-time, storage
- ✅ E-mail templates, landing page editor, blog, static pages, sitemap, support tickets, announcements, cookie banner, maintenance mode
- ✅ System status (scheduler/queue heartbeats), log viewer, database backups with nightly job
- 🟡 Payment drivers tested with simulated responses only, not against live sandboxes
- ✅ Admin can set a restaurant's subdomain / custom domain, run the DNS check or mark it verified by hand (restaurant detail page)

## 4. Restaurant onboarding and subscription (Phase 4)
- ✅ Plan choice at sign-up (trial / free / pay after verification), 3-step setup wizard, restaurant profile and branding
- ✅ Subscription page: usage versus limits, plan switch, checkout with coupon and gateway choice, invoices (PDF), cancel at period end
- ✅ Warnings: no plan, trial or plan ending, overdue, limits at 80% and 100%
- ✅ Team: invite by e-mail (person sets own password), change role, switch off (ends sessions), resend, remove, plan staff limit, custom roles with permission picker, owner/self protections
- ✅ Owner Domains screen: subdomain (reserved names, uniqueness, live preview), custom domain on plans with the feature, TXT-record (or CNAME) verification, platform DNS instructions

## 5. Menu management (Phase 5)
- ✅ Categories, products, photos, price and old price, calories, prep time, 14 EU allergens, diet labels, availability switches, featured
- ✅ Option groups (single/multiple, required, max, price per option), reusable across products
- ✅ Per-language names and descriptions with tabs, drag-and-drop order (and keyboard buttons), duplicate product
- ✅ Plan limits enforced; usage shown on the subscription page
- ✅ Stock tracking (portions count down with orders, return on cancel, auto sold-out, low-stock warning), one-screen prices & stock editor, percentage price change, CSV export of the menu
- ⬜ CSV import, scheduled availability (e.g. breakfast only), product photo gallery, nutrition beyond calories

## 6. Tables and QR codes (Phase 6)
- ✅ Areas and tables (single and bulk), unguessable table tokens with regenerate
- ✅ QR engine with own SVG/PNG renderer: square, rounded, dots, centre logo, colour contrast guard; decoded successfully in tests
- ✅ Downloads: PNG, SVG, ZIP of all tables, printable A4 PDF sheet with branded cards, plain menu QR
- ⬜ Table tent / other paper layouts, caption on PNG files

## 7. Appearance (Phase 6)
- ✅ 5 customer-menu themes, font, layout (list/cards/grid), corner style, photo toggle, live phone preview, hide "Powered by" on plans that include it

## 8. Customer menu (Phase 7, redesigned)
- ✅ QR table entry, restaurant domains, 503 page when the plan lapsed
- ✅ Hero, "Most loved" row, image-first cards, built-in illustrations for dishes without photos (14 motifs), sticky category bar with scroll tracking
- ✅ Search (accent-insensitive), diet filters, "hide dishes containing allergen" filters
- ✅ Product sheet with options, quantity, kitchen note; fly-to-cart animation; cart with "goes well with" suggestions; cart kept in the browser
- ✅ Languages (query, cookie, browser), right-to-left, cache with automatic invalidation, no-JS fallback list
- ✅ Server-side re-pricing of every cart (prices from the browser are ignored)
- 🟡 Real dish photos are up to the restaurant; illustrations are a fallback, not photography

## 9. Orders (Phase 8)
- ✅ Guest checkout: dine in / takeaway / delivery, contact details, payment on the spot (cash or card), tax / service charge / delivery fee / delivery minimum
- ✅ Double-tap protection, rate limit, running order numbers, snapshot of items so menu edits never rewrite history
- ✅ Guest tracking page with live status, estimated time, cancel while new
- ✅ Live board for kitchen/waiters/cashiers: polling, sound and desktop alerts, late markers, role-based actions, mark paid, pause ordering, 80 mm ticket print, order detail with history
- ✅ Ordering settings: types, fees, payment methods, auto-accept, preparation time, guest cancel
- ✅ Staff order entry (POS) for waiters, cashiers, managers: menu browser with options, table / takeaway / delivery, optional "paid now" for cashiers; server re-prices everything
- ✅ Optional guest e-mail at checkout: order received, ready, cancelled-by-restaurant e-mails (admin-editable templates; a mail outage never blocks an order)
- ⬜ Online card payment for orders (not verifiable without live gateway accounts), SMS notifications (needs a provider), courier tracking

## 9b. Menu depth and branches (v2 gap work)
- ✅ Dish fields: badges (new, spicy, chef's pick, …), nutrition, video link, gallery, schedule (days/hours), limited-time dates, order-type visibility, pairings ("goes well with")
- ✅ Sizes/variants with their own price, set menus (combos with one dish per slot and surcharges, picked dishes leave stock), multiple menus (breakfast, drinks) with their own hours and a switcher on the guest page
- ✅ CSV menu import with preview and plan limits
- ✅ Branches: per-location tables, staff (fixed or all), opening hours, price/availability/portion overrides per dish with copy between branches, guest branch choice (QR table → link → remembered → chooser), closed-branch protection, order board and till filtered by branch; plan limit `branches`

## 9c. QR, floor and appearance (v2 gap work)
- ✅ QR print: frames (border, ribbon, badge), five paper layouts (cards, small cards, stickers, folded table tent, poster), NFC link list with Web NFC "write to tag" and CSV
- ✅ Floor map: drag tables into place, shapes, live free / busy / ready / waiting-to-pay states, tap to order
- ✅ Sixth theme (Ocean), compact header, load-more-while-scrolling for long menus, optional guest light/dark switch
- ✅ Tablet mode for staff (sidebar folds away, larger text, screen stays awake) and a self-order kiosk (`?kiosk=1`: idle reset, thank-you screen, no delivery)
- 🟡 Web NFC writing only works in Chrome on Android; the kiosk has not been tried on real kiosk hardware

## 9d. Guest menu extras (v2 gap work)
- ✅ Installable menu app (web manifest, generated home-screen icons, offline-readable menu, orders/status never cached)
- ✅ Filters for highest price and spiciness (new 0–3 spice field), sorting by price, favourites (kept in the browser), remembered preferences, comfort modes (larger text, high contrast, reduced motion)
- ✅ Banners and one-time pop-ups with dates, link and image (Marketing → Banners)
- ✅ Approximate prices in other currencies (admin sets rates; cart and checkout always in the restaurant currency)
- ✅ Optional guest account: e-mailed sign-in link, order history, saved details, self-service erasure; the form never reveals which e-mails have ordered
- 🟡 Currency rates are typed in by the platform admin (no automatic rate feed); the service worker was tested for what it serves, not in a live browser install

## 9e. Ordering extras (v2 gap work)
- ✅ New order types: curbside pickup (car details) and room service (room number); packaging fee (per order and per item) on packed orders
- ✅ Pre-orders for a later time (lead time and days ahead set by the owner), item limit per guest order, waiting time that grows with the kitchen queue
- ✅ Shared table tab: every order of a table sitting is on one bill that all guests can see (no personal data shown)
- ✅ "Call the waiter / bill / water" requests from the guest menu, shown live on the order board
- ✅ Prep list (batching) that adds up open orders, with preparation stations (kitchen, bar…) set per dish
- ✅ "Order again" from a finished order; "On the way" step for deliveries
- ✅ Guest notifications when the order is ready / on the way: browser push (own VAPID keys, made on first use), SMS and WhatsApp through the messaging providers
- 🟡 Browser push, SMS and WhatsApp were tested against fakes only (no real push service or provider account)

## 9f. Payments (v2 gap work)
- ✅ Guests pay from their phone: whole bill, equal share (split), any amount (partial), or the whole table's tab in one go, with tip presets or a custom tip
- ✅ Restaurants connect their OWN gateway accounts (Stripe, PayPal, Mollie, iyzico, Razorpay, Paystack, Flutterwave, Mercado Pago, Midtrans); money goes straight to them. Plan feature `online_payments` gates it; the platform admin picks the allowed gateways
- ✅ Platform commission on online payments (bill amount, not tips), added as a line to the restaurant's next subscription invoice in the invoice currency
- ✅ Payment ledger: cash/card/online part payments, tips, refunds (Stripe through its API; others recorded and refunded in the provider's dashboard), orders re-open when a refund leaves them unpaid
- ✅ Digital receipt page + PDF with QR code (also on the staff ticket), e-mailed with the PDF when the bill is settled; "write a public review" invitation for happy guests with the owner's own link
- ✅ Apple Pay / Google Pay: shown by the hosted checkout pages when the owner has them on in their gateway account (nothing to configure here)
- 🟡 Gateway checkouts, webhooks and refunds are tested against simulated responses (signature checks included), not against live gateway accounts. Refunds through the API exist for Stripe only. Commission in a currency other than the plan's stays unbilled

## 9g. Operations (v2 gap work)
- ✅ Kitchen display (KDS): big tickets, timers, per-station "done" so a kitchen and a bar tick off their own lines; unaccepted-order alarm on the board and an e-mail to the owner after N minutes
- ✅ Till: staff discount on top of promo codes, close a whole table at once (from the order page or the floor map), part payments and splits
- ✅ Delivery areas with their own fee and minimum, couriers (assign, claim, "on the way", collect cash/card on delivery)
- ✅ Cash shifts (opening cash, expected vs counted, difference), order history with filters and CSV export, low-stock e-mail alerts
- ✅ Thermal printing: ESC/POS kitchen tickets and receipts queued in the cloud, collected by a small bridge program (docs/tools/print-bridge.php) next to the printer; staff screens installable as an app
- 🟡 Printing was tested to the byte stream and the queue protocol, not on a real printer; Arabic text does not print on most ESC/POS printers (no code page)

## 9h. Reservations (v2 gap work)
- ✅ Online booking page with live free times (opening hours, slot length, stay length, largest party, notice, days ahead), table-aware or seats-at-once capacity, manual or automatic confirmation
- ✅ Guest private link to view/cancel; e-mails for received, confirmed, cancelled and a reminder before the visit (editable templates)
- ✅ Staff day view: confirm, assign a table (smallest fitting one is picked), seat, finish, no-show, take phone bookings (even into a full slot, on purpose)
- ✅ **Deposits**: optional amount per guest, paid through the restaurant's own gateway (plan feature `online_payments`). The booking is "awaiting" and holds its table for a few minutes, then is confirmed by the verified payment (or goes to staff when manual confirmation is on); unpaid bookings are released automatically (`reservations:expire`); a late payment for a lost table is paid back. Cancel early (or by the restaurant) = refund (automatic for Stripe, otherwise "to pay back" for staff to do by hand and tick off); late cancel or no-show = restaurant keeps it; seated = counts towards the bill. Tested against faked Stripe responses only
- 🟡 `reservations` plan feature gates it. SMS/WhatsApp reminders go out with the e-mail reminder when a provider is set up (tested with faked HTTP). Staff-made bookings never need a deposit; the deposit is not deducted automatically on the order, staff see the amount

## 9i. Marketing growth (v2 gap work)

- ✅ **Campaigns by SMS and WhatsApp** through the existing messaging providers, with the same consent rules, daily cap and monthly message allowance; every text carries a signed stop link. Audiences by **segment**: new, regulars (silver tier), VIP (gold tier), lapsed. Recipients are marked `failed` when no provider is configured. Tested with faked provider HTTP only, no live SMS.
- ✅ **Happy hour / dynamic pricing**: percentage rules by weekday, time window (also past midnight) and category. Applied by the server in `CartPricing`, so the cart and the real order both carry the price; the menu shows a banner but **the dish cards themselves keep the regular price** (the cached menu tree is not time-dependent).
- ✅ **Gift cards** with a balance, expiry and an e-mail to the recipient; typed in the promo field, spent down atomically. They are issued by staff; there is **no online gift-card shop** for guests.
- ✅ **Tiers** (bronze/silver/gold by completed orders) feed campaign segments; the existing "every Nth order" reward remains the stamp card.
- ✅ **Autopilot** (`marketing:autopilot`, daily): personal single-use win-back code by e-mail to consenting lapsed guests, at most once per 90 days.
- ✅ **NPS**: optional 0-10 question next to the stars, score and breakdown on the reviews page.
- ✅ **Ad pixels** (Meta, Google tag, TikTok): ids validated by pattern, loaded on the guest menu only when the guest does not send Do Not Track. This is not a full cookie-consent banner.
- ✅ **Link-in-bio page** (`/links`), **printable A5 flyer** with the menu QR, **website button** script (`/widget.js`) and iframe snippet, **promo templates** (welcome, weekend, big order, flash sale).
- ✅ **Birthdays** (day and month only, never the year): guests add it on their account page; segment "birthday this month"; optional autopilot sends one personal code on the day, once a year, only with marketing consent
- ✅ **Saved segments** with free rules (order count range, spend, active/quiet for N days, new guests, birthday month, tier), live reach count, usable in campaigns
- 🔴 Not built: A/B testing (no open tracking to pick a winner), automatic send-time optimisation.

## 9j. Mini website and SEO (v2 gap work)

- ✅ Public **About page** (`/about`, optional): story, opening hours, address, phone, call/directions buttons, social links, rating; per-language texts
- ✅ **Search engines**: per-language title and description, canonical link, `hreflang` alternates, schema.org `Restaurant` data (address, phone, cuisine, price range, opening hours, rating) on the menu and About page, Open Graph tags
- ✅ Per-restaurant **`sitemap.xml`** (menu + About, every language, never table or order pages); one switch turns indexing off (`noindex`, empty sitemap)
- 🟡 `robots.txt` and `sitemap.xml` are served per restaurant only under `/r/{slug}/`; a restaurant's own domain root is handled by the landing route, so a domain-level `robots.txt` is not generated
- 🔴 Not built: page builder, blog, photo gallery, online table-booking widget on the About page

## 9k. Analytics extras (v2 gap work)

- ✅ **Menu engineering** (`/reports/menu`, plan feature `analytics`): stars / plowhorses / puzzles / dogs from popularity and margin, food-cost %, sales by category, CSV export
- ✅ **Weekly digest** e-mail to the owner every Monday (orders, revenue vs. the week before, best sellers); owner can switch it off
- 🟡 Margins use the dish's own cost price; extras and sizes are not costed, and dishes without a cost price are listed but not classified
- ✅ **Team & tables** page (`/reports/operations`): per person accepted/ready/completed/cancelled and average time to accept (signed-in actions only), table turnover (orders, revenue, average ticket, minutes from order to completed)
- 🔴 Not built: forecasting, scheduled custom reports; table minutes are a stand-in (order to completed), not seat time

## 9l. REST API and webhooks (v2 gap work)

- ✅ Plan feature `api`; **API tokens** (own implementation, no Sanctum): hashed, abilities, expiry, revoke, per-token rate limit
- ✅ **REST API v1**: menu (read, change price/availability/stock), orders (list, show, change status); documented in `docs/API.md`
- ✅ **Webhooks**: `order.created`, `order.status_changed`, `order.paid`; HMAC-SHA256 signed with timestamp, queued with retries, delivery log, test button, auto-off after 15 failures in a row; SSRF guard (public HTTPS only, no redirects)
- 🟡 Zapier/Make/n8n work through webhooks + API; no certified connector apps
- ✅ Also: place orders (with Idempotency-Key), reservations (list, book, change status), customers (read), `reservation.created` / `reservation.status_changed` webhooks, OpenAPI file at `/api/v1/openapi.json`
- 🔴 Not built: payments endpoints, creating customers, changing menu structure through the API
- Tested with faked HTTP only; receivers' behaviour on real networks is untested

## 9m. Add-on system (v2 gap work)

- ✅ `addons/<slug>/` with `addon.json`; code autoloaded under `Addons\...`, provider registered after the core (nav, routes, events, mail templates, migrations)
- ✅ Super admin page: install from a signed zip (same signature/hash checks and Ed25519 key as updates, paths limited to `addons/<slug>/`), switch on/off (migrations run on enable), upgrade in place, remove (tables stay)
- ✅ A broken add-on is skipped and reported instead of crashing the site; state kept in a file so it works before the database is up
- ✅ Other authors' add-ons install when their public key is listed in `ADDONS_TRUSTED_KEYS` (add-ons only; updates always need the vendor key). Copying a folder by hand works
- 🔴 Not built: add-on marketplace, per-add-on licence keys, dependency resolution between add-ons, migration rollback on uninstall
- Docs: `docs/ADDONS.md`, example in `docs/examples/hello-addon`

## 9n. Security and privacy (v2 gap work)

- ✅ Security headers on every page (nosniff, referrer policy, permissions policy, HSTS over https); panel and sign-in cannot be framed, the guest menu can (website button/iframe)
- ✅ Guests can **download their data** (JSON) and delete it; staff can export/delete customers; **retention period** per restaurant erases quiet guests and personal details of old orders every night (sales figures stay)
- ✅ **Consent banner** for ad pixels (Accept/No thanks, remembered per device, nothing loads before consent or with Do Not Track)
- ✅ System page now also checks HTTPS, update signing key, real queue and demo mode
- ✅ Existing: two-factor sign-in, login throttling, reCAPTCHA option, activity log, tenant isolation tests, signed updates, SSRF guard for webhooks
- 🔴 Not built: full Content-Security-Policy (pages use inline scripts), cookie-consent management for non-ad cookies (the menu sets only functional ones), formal GDPR/KVKK certification or legal texts (owners must supply their own privacy policy)

## 9o. Packaging and documentation (v2 gap work)

- ✅ Signing tools: `php artisan package:keys` and `package:build` (updates and add-ons, Ed25519, file hashes); `docs/tools/build-release.sh <version>` builds the buyer package (not executed in this environment, syntax-checked only)
- ✅ Manual: installation (shared hosting + VPS), owner guide, admin guide, updating, API, add-ons, developer guide, troubleshooting, generated HTML manual (`php artisan docs:build`), third-party licence list (`docs:licenses`, with LGPL notes), changelog, README, marketplace listing text (`docs/CODECANYON.md`)
- 🟡 Guides were written from the code and tests, not from screenshots; no video tutorials, no screenshots in the manual
- 🔴 Not done: the actual marketplace submission package/screenshots/preview images, legal review of licences, a real Envato licence check against the live API (code exists, never run against Envato)

## 9p. PHP 8.2 and running without a terminal (v2 gap work)

- ✅ **PHP 8.2**: `config.platform.php` is pinned to 8.2.0 so Composer only picks 8.2-compatible packages (Intervention Image 3, Spatie Permission 6, Pint 1.30 instead of the 8.3-only majors); the code uses no 8.3+ syntax. Verified by dependency resolution and a code search; the test suite itself ran on PHP 8.3.6 only (no 8.2 binary here)
- ✅ **Web cron**: secret address `/cron/<token>` runs due scheduled tasks **in-process** (no `php artisan` sub-process, which fails under FastCGI or with `exec` disabled) and the queue for up to 45 s; one run at a time
- ✅ **Fallback without any cron**: after a visitor's page is sent, runs the same once a minute (modes: automatic when no cron was seen, always, off)
- ✅ **Admin "server tools"**: update database, optimise, storage link, retry/flush failed jobs, back up now, run scheduler/queue now, cache clears; fixed allowlist
- ✅ Installer creates the storage link; if symlinks are forbidden, uploads are served by a built-in `/storage/...` route (images and PDFs only)
- 🟡 The visitor-driven fallback does its work after the response only on PHP-FPM/FastCGI; on mod_php the visitor waits a moment. A real cron or the web address is still better

## 9q. Market gaps closed after the competitor study (v2 gap work)

- ✅ **Azerbaijani and Russian** for everything guests see (menu, ordering and tracking, reservations, marketing and review pages, allergen/diet labels) plus AZN and RUB currencies; the guest-translation tests cover them like Turkish and Arabic. 🟡 Written by the developer, **not reviewed by native speakers**; the restaurant panel and platform admin stay English (Turkish/Arabic panel texts are partial too). Russian plurals use explicit ranges (1, 2-4, 5+), so 11-14 read slightly off
- ✅ **QR scan and menu-view analytics** (`/reports/visits`): hourly counters of table-QR scans and menu views, per day, per hour, per table, orders per 100 views. No cookies or visitor data are stored; reloads within 30 minutes and crawlers are not counted; kiosks are excluded
- ✅ **Monthly plan limits**: `orders_per_month` (guest and API orders stop when used up; staff orders still go through; cancelled orders do not count) and `scans_per_month` (the menu shows "unavailable" for the rest of the month). Empty = unlimited, shown with the other usage bars
- ✅ **Local payments and SMS**: PayTR (Turkey, iFrame API, hex-encoded order ids, plain-text "OK" reply) and Epoint (Azerbaijan, signed `data`/`signature` requests, status re-check on return) as subscription and restaurant gateways; Netgsm and İleti Merkezi SMS providers. Tested with faked HTTP only: check each in the provider's sandbox before selling.
- ✅ **Excel (.xlsx) menu import** through the same preview as CSV (first sheet, size-capped).
- ✅ **Push notification campaigns**: guests tap "Get offers" on the menu, the browser permission is the consent; campaign channel "Push notification" sends to all subscribers; expired browsers are removed.
- ✅ **Inventory and recipes** (`/inventory`): ingredients with units, stock and warning level, suppliers, deliveries with weighted-average unit cost, recipes per dish; ordering uses up ingredients (never blocks an order), cancelling gives them back, and the dish's cost price follows the recipe.
- ✅ **Valet request** button next to waiter, bill and water.
- ✅ **Performance**: guest menu and staff list pages no longer query once per dish (currency and restaurant lookups); a query-budget test guards it.
- ✅ **Menu themes studio** (Super admin, Content, Menu themes): every theme (shipped or your own) is editable: colours with live preview, font, layout, corners, background (solid, gradient, mesh), card style (flat, soft, glass, glowing outline), scroll bar style, reveal-on-scroll animation, reading progress bar, moving background. Duplicate, switch off, make default, restore the original or delete. Three new dynamic themes: **Aurora** (mesh gradient, frosted cards, animated gradient scroll rail), **Neon** (glowing outlines and rail, zoom reveal) and **Sunset** (warm gradient, pill scroll bar, fade reveal). Restaurants can override scroll bar, reveal, card style, progress bar and moving background in Appearance. All motion stops for guests who prefer reduced motion.
- ✅ **Themes, round two**: Aurora, Neon and Sunset gained background decoration (floating colour orbs, neon grid, soft dots), gradient buttons, gradient or glowing section headings and card hover effects (lift, tilt, glow). All of these are editable per theme by the super admin and overridable per restaurant.
- ✅ **Mobile and responsive pass**: every panel, admin, guest and public page was measured in a real browser at 320, 360 and 768 px (no sideways scrolling anywhere), grids no longer widen the page, long codes wrap, touch screens get 40-44 px targets, the header on phones moves the language buttons into the user menu, and the site header switches to the menu button below 1024 px.
- ✅ **Store** (restaurant: Settings, Store; admin: Billing, Store catalog and Store sales): every plan feature and every menu theme is a store item. For each one the platform chooses *free for everyone*, *with plans only* (as before) or *for sale*, and sets a price, how it is sold (one time, rented by the month, rented by the year), a free trial, the texts restaurants see, and whether it is listed. Restaurants buy or rent through the same payment gateways as subscriptions, get an invoice and receipt, can extend a rental before it ends and try an item free once. Plans can include items as before (new plan switches: own address on our domain, all premium themes); an item bought separately never costs extra if the plan already has it. Locked themes show "Unlock" in Appearance and fall back to the default theme when a rental ends. The platform can also give an item to a restaurant for a number of months or for good, take it back, and see store revenue. Own address (subdomain) is a store item that is free until you change it.
- 🔴 Still open from the study: Param and Azerbaijani bank gateways beyond Epoint, delivery marketplace connectors (Yemeksepeti, Getir, Trendyol, Wolt, Bolt), external POS connectors (Loyverse, Square, WOLVOX), legal e-invoice and fiscal register support, map-drawn delivery zones and live courier tracking, white-label store apps, Zapier/Make.

## 10. AI (Phase 9)
- ✅ Providers: OpenAI, Anthropic, Gemini; keys encrypted in admin settings; admin connection test
- ✅ Write dish descriptions (tone, ingredient hints), translate into the menu languages (fills only empty fields), suggest allergens and diet labels (always confirmed by a person)
- ✅ Menu import from pasted text with an editable preview, plan limits applied to the whole batch
- ✅ Monthly credits from the plan, per-task cost set by the admin, charged only for usable answers; prompt-injection safe handling
- 🟡 Providers tested with simulated responses only, not against live APIs
- ✅ Menu import from a photo (the model reads the picture; OpenAI, Anthropic and Gemini formats) and from text PDFs (read on the server; scanned PDFs are pointed to the photo import)
- ✅ Review reply drafts and sales tips (always for a person to read and edit; nothing is sent by itself), translate-the-whole-menu in the background that only fills empty fields, skips languages you locked and stops when credits run out
- ✅ Menu assistant on the guest menu (opt-in, answers only from the dishes on the menu right now, one credit per answer, capped per visitor); "guests also order…" suggestions computed from real orders (no AI)
- 🟡 Vision and chat were tested against simulated provider responses only. No dish-image generation or photo enhancement: not built

## 11. Design and quality
- ✅ One design system (ink / saffron / basil, QR-module identity), light and dark, right-to-left, mobile layouts, admin-chosen palette with contrast guards
- ✅ 548 feature tests (Pest), Pint code style, verified in a real browser (Playwright) for the main flows

## Not started (original plan)
- ✅ **Phase 10** marketing and CRM: customer records built from orders (search, filters, CSV export with formula protection, notes, erasure on request), restaurant promo codes applied at checkout (percent/fixed, minimum, dates, usage limits, previewed live), loyalty rewards (single-use code per guest after every Nth completed order, shown on the order page and e-mailed), guest ratings with staff replies and low-rating alerts, average rating on the menu, e-mail campaigns to opted-in guests (queued, daily cap, signed one-click unsubscribe, consent rechecked at send time)
  - 🟡 Marketing mail uses your SMTP account; keep the per-restaurant daily cap modest on shared hosting. No SMS/WhatsApp campaigns
- ✅ **Phase 11** analytics: sales, orders, average order and cancel rate with previous-period comparison, sales by day, best sellers, busy-hours heatmap, order type/payment/channel split, new vs returning customers, discounts, time to ready, custom periods in the restaurant's time zone, CSV export; plans without the analytics feature get the headline numbers for 7 days
- ⬜ **Phase 12** add-on infrastructure
- ⬜ **Phase 13** security, performance and compliance pass (data export/erasure, audit log, security headers, load checks)
- ⬜ **Phase 14** Envato packaging: documentation, changelog, licence list, update-signing tool

## Plan features that exist as switches but have no feature behind them yet
`custom_domain` (resolution only), `whatsapp_orders`, `analytics`.
