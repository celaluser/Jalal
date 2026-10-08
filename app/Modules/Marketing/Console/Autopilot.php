<?php

namespace App\Modules\Marketing\Console;

use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Models\Customer;
use App\Modules\Marketing\Models\PromoCode;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Daily "we miss you": guests who agreed to marketing and have been away for N days get a personal, single-use code.
 * Each guest is contacted at most once every 90 days, and only restaurants that switched it on take part.
 */
class Autopilot extends Command
{
    protected $signature = 'marketing:autopilot';

    protected $description = 'Send win-back messages to lapsed guests (restaurants that enabled it)';

    public function handle(TenantContext $tenant, MarketingSettings $settings): int
    {
        $sent = 0;

        Restaurant::query()->chunkById(100, function ($restaurants) use ($tenant, $settings, &$sent) {
            foreach ($restaurants as $restaurant) {
                $s = $settings->for($restaurant);

                if (! $s['autopilot_winback'] || $restaurant->isSuspended()) {
                    continue;
                }

                $tenant->runAs($restaurant, function () use ($restaurant, $s, &$sent) {
                    $lapsed = Customer::where('marketing_opt_in', true)->whereNull('unsubscribed_at')->whereNotNull('email')
                        ->where('last_order_at', '<', now()->subDays((int) $s['autopilot_days']))
                        ->where(fn ($q) => $q->whereNull('winback_at')->orWhere('winback_at', '<', now()->subDays(90)))
                        ->limit(100)->get();

                    foreach ($lapsed as $customer) {
                        $promo = PromoCode::create([
                            'code' => 'MISSYOU-'.strtoupper(Str::random(6)), 'description' => 'Win-back', 'type' => PromoCode::PERCENT, 'value' => (int) $s['autopilot_percent'],
                            'max_uses' => 1, 'ends_at' => now()->addDays(14), 'customer_id' => $customer->id,
                        ]);
                        $customer->forceFill(['winback_at' => now()])->save();
                        SafeMail::send($customer->email, new TemplatedMail('winback', [
                            'name' => $customer->name ?: '', 'restaurant' => $restaurant->name, 'code' => $promo->code, 'reward' => __('marketing.reward_percent', ['value' => $promo->value]),
                            'expires' => $promo->ends_at->toFormattedDateString(), 'menu_url' => $restaurant->publicUrl(),
                        ], $customer->locale));
                        $sent++;
                    }
                });
            }
        });

        $this->info("Sent {$sent} win-back message(s).");

        return self::SUCCESS;
    }
}
