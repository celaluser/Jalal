<?php

return [
    /*
    | Lock file written when installation succeeds. While it is missing every web request
    | is redirected to /install. Delete it only if you really want to run the installer again.
    */
    'lock_file' => storage_path('app/installed'),

    /*
    | Treat the app as installed regardless of the lock file (used by the test suite and CI).
    */
    'force_installed' => (bool) env('APP_INSTALLED', false),

    'min_php' => '8.2.0',

    'extensions' => [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'json', 'mbstring',
        'openssl', 'pdo', 'tokenizer', 'xml', 'zip',
    ],

    /** Paths (relative to the project root) that must be writable. */
    'writable' => ['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'],
];
