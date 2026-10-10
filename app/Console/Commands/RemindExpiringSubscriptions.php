<?php

namespace App\Console\Commands;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

class RemindExpiringSubscriptions extends Command
{
    protected $signature = 'billing:remind {--days=3 : Remind this many days before the end}';

    protected $description = 'E-mail restaurant owners whose plan or trial is about to end (once per period)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $sent = 0;

        Subscription::allTenants()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->whereNull('canceled_at')->whereNull('reminder_sent_at')
            ->whereBetween('ends_at', [now(), now()->addDays($days)])
            ->each(function (Subscription $sub) use (&$sent) {
                $restaurant = Restaurant::with('owner')->find($sub->restaurant_id);
                $owner = $restaurant?->owner;

                if (! $owner) {
                    return;
                }

                $delivered = SafeMail::send($owner, new TemplatedMail('subscription_expiring', [
                    'name' => $owner->name,
                    'restaurant' => $restaurant->name,
                    'plan' => $sub->plan->name,
                    'ends_at' => $sub->ends_at->toDateString(),
                    'days_left' => (string) max(0, (int) ceil(now()->floatDiffInDays($sub->ends_at, false))),
                    'billing_url' => route('dashboard'),
                ], $owner->locale));

                // Mark it even when the template is switched off, so it is not re-evaluated every day.
                $sub->forceFill(['reminder_sent_at' => now()])->save();
                $sent += (int) $delivered;
            });

        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
