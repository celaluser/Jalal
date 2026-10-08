# Updating

## Applying an update

1. Back up the files and database (**Admin → Backups** makes a database backup).
2. **Admin → Updates**: upload the signed `.zip` from the vendor. The signature, every file hash and every path are checked before anything is written. Files outside the allowed folders, symlinks, `.env` and `storage/` are never touched.
3. Review the notes and confirm with your password. Your files that the update replaces are saved to a backup first; migrations run; the site is in maintenance mode for a few seconds.
4. If copying files or the migrations fail, the update rolls itself back from that backup and says so. The backup zip is kept for a manual restore.

Needs `UPDATER_PUBLIC_KEY` in `.env` (the vendor's public key).

## For the vendor: building packages

Run these on your own machine, never on a customer's server:

```
php artisan package:keys --out=package-signing.key        # once; keep the file offline and out of git
# put the printed UPDATER_PUBLIC_KEY=... line in the .env of every installation (or in .env.example before you sell)
php artisan package:build ./dist/changed-files dist/update-1.1.0.zip --ver=1.1.0 --key=package-signing.key --notes="Fixes"
php artisan package:build ./my-addon dist/my-addon-1.0.0.zip --ver=1.0.0 --key=package-signing.key --addon=my-addon
```

The source folder of an update mirrors the project tree and may contain only: `addons`, `app`, `bootstrap`, `config`, `database`, `lang`, `public`, `resources`, `routes`, `vendor`, `composer.json`, `composer.lock`, `artisan`.
Include `config/version.php` with the new version number: it is compared against the installed version, and an update never goes backwards.

## Release checklist

1. `composer install --no-dev -o`, `npm ci && npm run build`
2. `php artisan test`
3. Update `CHANGELOG.md`, `config/version.php`
4. `php artisan docs:licenses && php artisan docs:build`
5. `docs/tools/build-release.sh 1.1.0` creates the full package for new buyers; build the update zip for existing buyers as above.
