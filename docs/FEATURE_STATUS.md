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

## 10. AI (Phase 9)
- ✅ Providers: OpenAI, Anthropic, Gemini; keys encrypted in admin settings; admin connection test
- ✅ Write dish descriptions (tone, ingredient hints), translate into the menu languages (fills only empty fields), suggest allergens and diet labels (always confirmed by a person)
- ✅ Menu import from pasted text with an editable preview, plan limits applied to the whole batch
- ✅ Monthly credits from the plan, per-task cost set by the admin, charged only for usable answers; prompt-injection safe handling
- 🟡 Providers tested with simulated responses only, not against live APIs
- ⬜ Photo-to-menu (OCR), dish image generation, customer-facing AI assistant, review replies and sales insights (costs already configurable, features come with Phases 10 and 11)

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
`custom_domain` (resolution only), `whatsapp_orders`, `online_payments` (for orders), `reservations`, `analytics`.
