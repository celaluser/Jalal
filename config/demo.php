<?php

return [
    /*
    | Demo mode blocks destructive and settings-changing requests so a public live demo cannot
    | be vandalised. Safe (GET/HEAD) requests and the routes below keep working.
    */
    'enabled' => (bool) env('DEMO_MODE', false),

    'allowed_routes' => ['login', 'logout', 'password.email', 'two-factor.challenge'],
];
