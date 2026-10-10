# Restaurant owner guide

## First steps

1. After signing up, the setup wizard asks for your restaurant name, currency, languages and a logo. You can skip it and return later.
2. **Menu**: create categories, then dishes with a price, photo, description and options (sizes, extras, combo menus). Hide a dish, mark it sold out, or set opening hours per dish or category.
3. **Tables & QR**: add tables and download QR codes (PDF sheets, table tents, NFC cards). Print them. Guests scan and see your menu in their language.
4. **Appearance**: pick a theme, colours, hero style and layout. Check the menu on your phone.
5. **Ordering** (Settings): choose which order types you accept (dine-in, takeaway, delivery, curbside, room service), payment methods, service charge, tax, minimum delivery order, opening hours and the "paused" message.

## Taking orders

- New orders appear on **Orders**; the page refreshes by itself and plays a sound. Accept → Preparing → Ready → Completed, or cancel with a reason.
- **Kitchen display** shows dishes per station for the kitchen and bar. **New order** (POS) lets staff type orders in.
- Guests follow their order on a private link and can get updates by SMS/WhatsApp/push when your plan and providers allow it.
- Print tickets from the order page, or set up automatic printing with the print bridge (see `docs/tools/print-bridge.php`).
- **Payments**: cash, card at table and optional online payments with your own Stripe/PayPal/etc. account. Split bills, tips, refunds (Stripe) and shift reports are in the order and Payments pages.

## Reservations

Turn on **Reservations** (plan feature) to let guests book a table online. Set slot length, stay length, notice period and opening hours. Confirm, seat and mark no-shows on the day view.

**Deposits**: set an amount per guest in *Reservation settings* (needs online payments with your own gateway account). Guests pay right after booking, the table is held for the minutes you choose, and unpaid bookings are released by themselves. Cancel early and the deposit goes back (automatically with Stripe; with other gateways the booking shows *to pay back* and you refund it in the gateway's dashboard, then press *Mark as paid back*). Late cancellations and no-shows keep the deposit. When the guest comes, the day view shows the deposit so you can take it off the bill.

## Marketing

- **Customers** are created from orders. Guests tick a box to receive marketing; you can only mail those who agreed, and every message has a stop link.
- **Promo codes**, **loyalty rewards** (every Nth order), **gift cards**, **happy hour** pricing, **banners and pop-ups**.
- **Campaigns** by e-mail, SMS or WhatsApp to a segment (new, regulars, VIP, lapsed, birthday this month, or your own **Segments** built from simple rules such as "3+ orders and quiet for 60 days"). Your plan's monthly message allowance applies to SMS/WhatsApp.
- **Reviews**: guests rate after completed orders (stars and a 0-10 recommendation question). Reply from **Reviews**; send happy guests to your Google review link.
- **Website & SEO**: a small About page, search titles and descriptions, opening hours for Google. **Loyalty & reviews** page also has the link-in-bio page, the printable flyer and the website button for your own site.

## Reports

**Reports** shows sales, best sellers, busy hours and customers; **QR scans & views** shows how often your menu was opened and which tables are scanned; **Team & tables** shows what your team did and how fast tables turn. **Menu engineering** classifies dishes into stars, plowhorses, puzzles and dogs (enter each dish's cost price for margins). A weekly summary e-mail arrives on Mondays; switch it off on the Loyalty & reviews page.

## Team and branches

Invite staff and give them a role (manager, waiter, kitchen, cashier, bar, delivery). Several branches can have their own tables, prices, sold-out switches and stock.

## Your data and your guests' data

- Export customers as CSV; delete a customer to erase their personal details (their orders stay as anonymous sales).
- Guests can download or delete their own data from their account page.
- Set an automatic erase period under **Loyalty & reviews → Privacy**.
- Add your own privacy policy and terms pages. This product provides tools; it does not provide legal advice.

## Getting help

**Support** opens a ticket with the platform team.

## Inventory and recipes
Open **Inventory & recipes** in the menu group. Add ingredients (unit, stock, warning level), record deliveries (this updates the cost per unit as a weighted average), and open **Recipes** to say what goes into one portion of each dish. From then on every order uses up the ingredients, a cancelled order gives them back, and the dish's cost price (used by the menu engineering report) follows the recipe. Orders are never blocked by missing ingredients; the stock simply shows a negative number so you can recount.
