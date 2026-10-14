<?php

return [

    /*
    | Domain the SaaS itself is served from (no scheme, no port). Subdomains of it
    | (restaurant.example.com) resolve to restaurants when subdomains are enabled.
    */
    'base_domain' => env('TENANCY_BASE_DOMAIN'),

    /*
    | Hosts that always belong to the platform (landing page, super admin) and
    | never resolve to a restaurant.
    */
    'central_domains' => array_filter(array_map('trim', explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'localhost,127.0.0.1')))),

    'subdomains_enabled' => (bool) env('TENANCY_SUBDOMAINS', false),

    'custom_domains_enabled' => (bool) env('TENANCY_CUSTOM_DOMAINS', false),

    /*
    | Path prefix for the always-available slug based menu URL: /r/{slug}.
    */
    'path_prefix' => 'r',

    /*
    | Team id used by spatie/laravel-permission for platform level users (super admins),
    | who do not belong to a restaurant.
    */
    'platform_team_id' => 0,
];
