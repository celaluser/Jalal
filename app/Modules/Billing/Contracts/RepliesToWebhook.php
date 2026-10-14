<?php

namespace App\Modules\Billing\Contracts;

use Illuminate\Http\Response;

/** A gateway that wants its own acknowledgement body for a processed webhook (PayTR insists on a plain "OK"). */
interface RepliesToWebhook
{
    public function webhookReply(): Response;
}
