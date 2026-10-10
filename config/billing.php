<?php

return [
    /*
    | Days an unpaid active subscription keeps working (status past_due) after its end date
    | before it is marked expired.
    */
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),

    'invoice_prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),
];
