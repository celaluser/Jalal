<?php

namespace App\Modules\Admin\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Support\Models\Ticket;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function currency(): string
    {
        return (string) $this->settings->get('billing.currency', 'USD');
    }

    /**
     * @return array<string, int|float>
     */
    public function totals(): array
    {
        $paying = Subscription::allTenants()->where('status', 'active')->where('price', '>', 0);

        return [
            'restaurants' => Restaurant::count(),
            'suspended' => Restaurant::where('status', Restaurant::STATUS_SUSPENDED)->count(),
            'active_subscriptions' => (clone $paying)->count(),
            'trials' => Subscription::allTenants()->where('status', 'trialing')->where('ends_at', '>', now())->count(),
            'new_30d' => Restaurant::where('created_at', '>=', now()->subDays(30))->count(),
            'mrr' => $this->mrr(),
        ];
    }

    /**
     * @return Collection<int, Restaurant>
     */
    public function latestRestaurants(int $limit = 5)
    {
        return Restaurant::with('owner')->latest('id')->limit($limit)->get();
    }

    /**
     * Tickets waiting for the support team (open = the restaurant wrote last).
     *
     * @return array{count: int, latest: Collection<int, Ticket>}
     */
    public function openTickets(int $limit = 5): array
    {
        $open = Ticket::allTenants()->where('status', 'open');

        return ['count' => (clone $open)->count(), 'latest' => (clone $open)->latest('last_reply_at')->limit($limit)->get()];
    }

    /**
     * Monthly recurring revenue in the platform currency: monthly plans count in full, yearly plans
     * as one twelfth. Lifetime, free and trial subscriptions are not recurring revenue.
     */
    public function mrr(): float
    {
        $sum = Subscription::allTenants()
            ->where('status', 'active')
            ->where('currency_code', $this->currency())
            ->selectRaw("SUM(CASE interval WHEN 'monthly' THEN price WHEN 'yearly' THEN price / 12.0 ELSE 0 END) AS mrr")
            ->value('mrr');

        return round((float) $sum, 2);
    }

    /**
     * New restaurants per day for the last $days days, oldest first, zero-filled.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function signupsByDay(int $days = 30): array
    {
        $counts = Restaurant::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->select(DB::raw('DATE(created_at) AS d'), DB::raw('COUNT(*) AS c'))
            ->groupBy('d')->pluck('c', 'd');

        $labels = $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $labels[] = $day;
            $values[] = (int) ($counts[$day] ?? 0);
        }

        return compact('labels', 'values');
    }

    /**
     * Paid invoice totals per month for the last $months months, oldest first, zero-filled.
     * Only invoices in the platform currency are summed, so the axis is one unit.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function revenueByMonth(int $months = 12): array
    {
        $from = now()->subMonths($months - 1)->startOfMonth();

        $totals = [];
        Invoice::allTenants()->where('status', 'paid')->where('currency_code', $this->currency())
            ->where('paid_at', '>=', $from)->select('paid_at', 'total')->each(function (Invoice $invoice) use (&$totals) {
                $key = $invoice->paid_at->format('Y-m');
                $totals[$key] = ($totals[$key] ?? 0) + (float) $invoice->total;
            });

        $labels = $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $labels[] = $month->format('Y-m');
            $values[] = round($totals[$month->format('Y-m')] ?? 0, 2);
        }

        return compact('labels', 'values');
    }
}
