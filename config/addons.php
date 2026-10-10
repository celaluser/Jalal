<?php

return [
    /*
    | Where add-ons live. Each add-on is a folder: addons/<slug>/addon.json plus its code under src/.
    | Add-ons are installed from signed packages (the same format and key as updates, see docs/ADDONS.md).
    */
    'path' => base_path('addons'),

    /** Remembers which add-ons are switched on. A plain file, so it works before the database is ready. */
    'state_file' => storage_path('app/addons.json'),

    /**
     * Extra public keys (base64 Ed25519) whose signed add-on packages may be installed, besides the vendor key in
     * UPDATER_PUBLIC_KEY. Lets you trust specific add-on authors: ADDONS_TRUSTED_KEYS=key1,key2. Updates never use these.
     */
    'trusted_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADDONS_TRUSTED_KEYS', ''))))),

    /** Turn the upload form off (for example on a public demo, or when you install add-ons by FTP only). */
    'upload_enabled' => (bool) env('ADDONS_UPLOAD', true),
];
