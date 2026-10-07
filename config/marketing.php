<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Campaign e-mail ceiling
    |--------------------------------------------------------------------------
    | The most marketing e-mails one restaurant may send per day, whatever it sets itself. Protects the
    | sending reputation of the shared mail account. Raise it if you use a transactional mail provider.
    */
    'daily_email_cap' => (int) env('MARKETING_DAILY_EMAIL_CAP', 500),
];
