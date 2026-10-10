# Marketplace material

Made from the demo install (`php artisan db:seed --class=DemoSeeder`). Not part of the buyer package.

| File | Use | Size |
| --- | --- | --- |
| `icon-80x80.png` | item icon | 80 x 80 |
| `preview-590x300.png` | main preview image | 590 x 300 (drawn at 2x) |
| `themes-1280x720.png` | extra preview: the four menu themes | 1280 x 720 |
| `screenshots/*.png` | numbered screenshots of landing page, admin, restaurant panel, orders, kitchen display, floor map, reports, inventory, campaigns, reservations, theme studio | 1440 x 900 |
| `screenshots/phone-*.png` | guest menu on a phone in each theme | 780 x 1688 |

Check the current size rules on Envato's author pages before uploading; they change. The Envato listing text is in `docs/CODECANYON.md`.

Regenerate after UI changes: start the demo, then run the Playwright scripts described in `docs/DEVELOPER.md` (responsive check) with a 1440 x 900 viewport.
