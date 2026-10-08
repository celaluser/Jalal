# Troubleshooting

| Problem | Likely cause and fix |
| --- | --- |
| White page / 500 error | Set `APP_DEBUG=true` briefly, or read `storage/logs/laravel.log` (Admin → Logs). Check `storage/` and `bootstrap/cache/` are writable. |
| Redirected to `/install` again | `storage/app/installed` is missing (not writable at install time, or deleted). |
| E-mails do not arrive | Admin → Settings → Mail; use "send test". Check spam. With the database queue, the cron/queue worker must run. |
| "Scheduler / queue: never" on System status | No cron job or worker is running. Easiest fix without a terminal: call the secret `/cron/...` address shown on that page once a minute, or set the fallback mode to "Always". See `INSTALLATION.md`. |
| Uploaded images are broken | Admin → System status → *Fix uploaded images*. If the host forbids symlinks the script serves `/storage/...` itself; make sure `storage/app/public` is writable. |
| New orders do not make a sound | Browsers block sound until the page has been clicked once. Click anywhere on the Orders page. |
| QR code opens a "not found" page | The restaurant slug changed or the table was deleted; download the QR code again. |
| Subdomain / custom domain does not work | Needs wildcard DNS or a DNS record per domain, a certificate, and `TENANCY_*` settings. `/r/slug` always works. |
| Payment webhook never arrives | The gateway must reach `https://your-domain/...`; check the webhook URL in the gateway's dashboard and that HTTPS works. |
| "Update refused: no public key" | Add `UPDATER_PUBLIC_KEY` to `.env`. |
| SMS / WhatsApp not sent | Choose a provider (Admin → SMS & WhatsApp), enter keys, check the plan's monthly allowance and that guests' numbers have a country code (set the calling code in Loyalty & reviews). |
| Arabic text prints as boxes on a receipt printer | Most ESC/POS printers cannot print Arabic. Use the browser print of the order page instead. |
| Webhook endpoint switched itself off | 15 deliveries in a row failed. Fix your endpoint, then turn it on again in API & webhooks. |
| Images do not show after moving servers | Run `php artisan storage:link`. |

Still stuck? Open a ticket with the platform or the vendor's support channel and include the last lines of `storage/logs/laravel.log`.
