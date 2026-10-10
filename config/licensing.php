<?php

return [
    /*
    | How purchase codes are verified:
    |   server : POST to your own licence server (LICENSE_SERVER_URL). Recommended: your Envato
    |            personal token must never ship inside the script.
    |   envato : call the Envato API directly with ENVATO_PERSONAL_TOKEN (for use on your own server).
    |   format : only checks the code format. Development and demos only.
    */
    'driver' => env('LICENSE_DRIVER', 'format'),

    'server_url' => env('LICENSE_SERVER_URL'),
    'server_key' => env('LICENSE_SERVER_KEY'),

    'envato_token' => env('ENVATO_PERSONAL_TOKEN'),
    'envato_item_id' => env('ENVATO_ITEM_ID'),

    'timeout' => 10,
];
