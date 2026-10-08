# Add-ons

Add-ons extend the application without touching its code. They live in `addons/<slug>/` and are installed from the super admin panel
(*System → Add-ons*) or by copying the folder yourself.

## Layout

```
addons/hello-addon/
  addon.json                 slug, name, version, author, description, min_core, namespace, provider
  src/                       PSR-4 root for the namespace in addon.json (must start with "Addons\")
  database/migrations/       run when the add-on is switched on
  resources/views/           register with $this->loadViewsFrom() in your provider
```

A complete example is in `docs/examples/hello-addon`.

## What an add-on can use

The service provider named in `addon.json` is registered after all core providers, so it can use:

- `RestaurantNav::add()` / `AdminNav::add()` – menu entries in the restaurant panel / super admin panel
- Routes (`loadRoutesFrom`, or `Route::...` directly), views, translations, migrations
- Events such as `OrderPlaced`, `OrderStatusChanged`, `OrderPaid` (App\Modules\Orders\Events)
- `EmailTemplateRegistry::register()` – editable e-mail templates
- `DemoDataRegistry::register()` – demo content
- Models with `BelongsToRestaurant` for tenant-safe data. Always use it for restaurant data.
- The REST API and webhooks for anything that lives outside PHP

## Installing

1. Back up the files and the database.
2. *System → Add-ons → Install*: upload the signed `.zip`. The signature and every file hash are verified first; nothing is written if a check fails.
3. Switch it on. Its migrations run now. A broken add-on is skipped at start-up and shown with its error instead of taking the site down.
4. To remove: *Remove*. The code is deleted; its database tables stay so a reinstall brings the data back.

Set `ADDONS_UPLOAD=false` in `.env` to switch the upload form off (install by FTP only). The form is also off on demo sites.

## Packaging (for add-on authors)

An add-on package is an update package (see the update documentation): `manifest.json` with the SHA-256 of every file, `manifest.sig` (Ed25519 signature of the manifest),
and the files under `files/addons/<slug>/…`. Every path must be inside `addons/<slug>/`. Packages are verified with the same public key as updates
(`UPDATER_PUBLIC_KEY`). To allow other authors, add their public keys to `ADDONS_TRUSTED_KEYS` in `.env` (comma separated); those keys are accepted for add-ons only, never for updates. Anyone can still copy a folder into `addons/` by hand.

## Security

An add-on is PHP code with full access to the application and database. Install only what you trust, and keep backups. Signing proves who built a package, not that the code is safe.
