<?php

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Services\ReportService;
use App\Modules\Core\Mail\SafeMail;
use App\Modules\Core\Mail\TemplatedMail;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Console\Command;

/** Monday morning: the owner gets last week's numbers by e-mail (if the restaurant had orders and has not switched it off). */
class WeeklyDigest extends Command
{
    protected $signature = 'reports:digest';

    protected $description = 'E-mail last week\'s sales summary to restaurant owners';

    public function handle(TenantContext $tenant, ReportService $reports, MarketingSettings $settings): int
    {
        $sent = 0;

        Restaurant::with('owner')->chunkById(100, function ($restaurants) use ($tenant, $reports, $settings, &$sent) {
            foreach ($restaurants as $restaurant) {
                if ($restaurant->isSuspended() || ! $restaurant->owner?->email || ! $settings->get($restaurant, 'weekly_digest')) {
                    continue;
                }

                $tenant->runAs($restaurant, function () use ($restaurant, $reports, &$sent) {
                    $period = $reports->period($restaurant, '7');
                    $r = $reports->build($restaurant, $period, true);

                    if ($r['orders'] === 0) {
                        return;
                    }

                    $arrow = fn (?float $d) => $d === null ? '–' : ($d > 0 ? '+' : '').number_format($d, 1).'%';
                    $top = collect($r['top'])->take(3)->map(fn ($t) => '- '.$t['name'].' ×'.$t['qty'])->implode("\n");
                    SafeMail::send($restaurant->owner->email, new TemplatedMail('weekly_digest', [
                        'name' => $restaurant->owner->name ?: '', 'restaurant' => $restaurant->name, 'from' => $period['from']->toFormattedDateString(), 'to' => $period['to']->toFormattedDateString(),
                        'orders' => (string) $r['orders'], 'revenue' => $restaurant->money($r['revenue'] / 100), 'average' => $restaurant->money($r['average'] / 100),
                        'revenue_change' => $arrow($r['delta']['revenue']), 'top' => $top, 'reports_url' => url('/reports'),
                    ], $restaurant->owner->locale ?? $restaurant->locale));
                    $sent++;
                });
            }
        });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
