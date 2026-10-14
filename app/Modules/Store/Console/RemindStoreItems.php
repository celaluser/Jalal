<?php

namespace App\Modules\Store\Console;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Store\Models\Entitlement;
use App\Modules\Store\Services\Catalog;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/** E-mails the owner a week before a rented store item or a trial ends. One e-mail per ending; extending the item re-arms it. */
class RemindStoreItems extends Command
{
    protected $signature = 'store:remind {--days=7}';

    protected $description = 'Remind owners of store items that end soon';

    public function handle(Catalog $catalog): int
    {
        $sent = 0;
        $rows = Entitlement::allTenants()->where('status', 'active')->whereNotNull('ends_at')->whereNull('reminded_at')
            ->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDays((int) $this->option('days')))->get();

        foreach ($rows as $e) {
            $restaurant = Restaurant::with('owner')->find($e->restaurant_id);
            $item = $catalog->find($e->item_slug);

            // Marked first: a failing mail server must not mean one e-mail per run.
            $e->forceFill(['reminded_at' => now()])->save();

            if (! $restaurant?->owner || ! $item) {
                continue;
            }

            SafeMail::send($restaurant->owner, new TemplatedMail('store_expiring', [
                'name' => $restaurant->owner->name, 'restaurant' => $restaurant->name, 'item' => $item['name'],
                'ends_at' => $e->ends_at->toDateString(), 'days_left' => (string) max(1, (int) ceil(now()->diffInHours($e->ends_at) / 24)),
                'store_url' => route('store.show', $item['slug']),
            ], $restaurant->owner->locale));
            $sent++;
        }

        $this->info("{$sent} reminders sent.");

        return self::SUCCESS;
    }
}
