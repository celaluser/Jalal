<?php

return [
    /*
    | Demo mode blocks destructive and settings-changing requests so a public live demo cannot
    | be vandalised. Safe (GET/HEAD) requests and the routes below keep working.
    */
    'enabled' => (bool) env('DEMO_MODE', false),

    /*
    | Shared password of all demo accounts; shown on the login page while demo mode is on.
    */
    'password' => env('DEMO_PASSWORD', 'demo-password'),

    'allowed_routes' => ['login.store', 'logout', 'two-factor.verify'],
];
