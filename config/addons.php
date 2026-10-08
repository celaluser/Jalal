<?php

return [
    /*
    | Where add-ons live. Each add-on is a folder: addons/<slug>/addon.json plus its code under src/.
    | Add-ons are installed from signed packages (the same format and key as updates, see docs/ADDONS.md).
    */
    'path' => base_path('addons'),

    /** Remembers which add-ons are switched on. A plain file, so it works before the database is ready. */
    'state_file' => storage_path('app/addons.json'),

    /** Turn the upload form off (for example on a public demo, or when you install add-ons by FTP only). */
    'upload_enabled' => (bool) env('ADDONS_UPLOAD', true),
];
