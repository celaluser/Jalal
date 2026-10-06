<?php

return [
    /*
    | Ed25519 public key (base64) used to verify update packages. Ship YOUR public key here and
    | keep the private key offline. Without a key, updates are refused unless
    | UPDATER_ALLOW_UNSIGNED=true (development only).
    */
    'public_key' => env('UPDATER_PUBLIC_KEY'),
    'allow_unsigned' => (bool) env('UPDATER_ALLOW_UNSIGNED', false),

    'max_entries' => 20000,
    'max_uncompressed_bytes' => 300 * 1024 * 1024,
    'max_upload_kb' => 102400,

    /** Top-level locations an update may write to. Everything else is rejected. */
    'allowed_roots' => ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor', 'composer.json', 'composer.lock', 'artisan'],

    /** Never overwritten, even inside an allowed root. */
    'protected' => ['.env', 'storage', 'public/storage', 'bootstrap/cache', 'database/database.sqlite'],

    'storage' => 'updates',
];
