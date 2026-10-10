<?php

namespace App\Modules\Store\Services;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Store\Models\Entitlement;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Support\Facades\DB;

/** What restaurants own from the store: buying, trying, granting, extending and ending. */
class Entitlements
{
    /** @var array<int, list<string>> */
    private array $cache = [];

    public function __construct(private readonly Catalog $catalog) {}

    public function forget(): void
    {
        $this->cache = [];
    }

    /** Slugs this restaurant owns right now. @return list<string> */
    public function liveSlugs(int $restaurantId): array
    {
        return $this->cache[$restaurantId] ??= Entitlement::allTenants()->where('restaurant_id', $restaurantId)->live()->pluck('item_slug')->unique()->values()->all();
    }

    public function owns(Restaurant $restaurant, string $slug): bool
    {
        return in_array($slug, $this->liveSlugs($restaurant->id), true);
    }

    /** The newest live entitlement of an item, to show "until ...". */
    public function current(Restaurant $restaurant, string $slug): ?Entitlement
    {
        return Entitlement::allTenants()->where('restaurant_id', $restaurant->id)->where('item_slug', $slug)->live()->orderByRaw('ends_at is null desc')->orderByDesc('ends_at')->first();
    }

    /** Has this restaurant tried (or owned) the item before? Trials are once per item. */
    public function everHad(Restaurant $restaurant, string $slug): bool
    {
        return Entitlement::allTenants()->where('restaurant_id', $restaurant->id)->where('item_slug', $slug)->exists();
    }

    /**
     * Gives the item. Extends what is running now; null months means for good.
     */
    public function grant(Restaurant $restaurant, string $slug, ?int $months, string $source = 'admin', ?int $invoiceId = null, ?int $days = null): Entitlement
    {
        return DB::transaction(function () use ($restaurant, $slug, $months, $source, $invoiceId, $days) {
            $running = $this->current($restaurant, $slug);

            if ($running && $running->ends_at === null) {
                return $running; // already for good: nothing to add
            }

            $forGood = $months === null && $days === null;
            $start = $running?->ends_at ?? now();
            $ends = $forGood ? null : ($days !== null ? $start->copy()->addDays($days) : $start->copy()->addMonthsNoOverflow((int) $months));

            if ($running) {
                $running->update(['ends_at' => $ends, 'invoice_id' => $invoiceId ?? $running->invoice_id, 'source' => $source === 'purchase' ? 'purchase' : $running->source]);
                $entitlement = $running;
            } else {
                $entitlement = (new Entitlement)->forceFill(['restaurant_id' => $restaurant->id, 'item_slug' => $slug, 'status' => 'active', 'source' => $source, 'starts_at' => now(), 'ends_at' => $ends, 'invoice_id' => $invoiceId]);
                $entitlement->save();
            }

            $this->forget();

            return $entitlement;
        });
    }

    /** Called when a store invoice is paid. Safe to call twice. */
    public function grantFromInvoice(Invoice $invoice): ?Entitlement
    {
        if ($invoice->store_slug === null) {
            return null;
        }

        if ($existing = Entitlement::allTenants()->where('invoice_id', $invoice->id)->first()) {
            return $existing;
        }

        $restaurant = Restaurant::withTrashed()->findOrFail($invoice->restaurant_id);
        $item = $this->catalog->find($invoice->store_slug);
        $months = $item && $item['billing'] === 'one_time' ? null : (int) ($invoice->store_months ?: 1);

        return $this->grant($restaurant, $invoice->store_slug, $months, 'purchase', $invoice->id);
    }

    public function startTrial(Restaurant $restaurant, string $slug): ?Entitlement
    {
        $item = $this->catalog->find($slug);

        if (! $item || ! $this->catalog->purchasable($item) || $item['trial_days'] < 1 || $this->everHad($restaurant, $slug)) {
            return null;
        }

        return $this->grant($restaurant, $slug, null, 'trial', null, $item['trial_days']);
    }

    public function revoke(Entitlement $entitlement): void
    {
        $entitlement->update(['status' => 'revoked']);
        $this->forget();
    }

    /** Marks ended rentals as expired (they stopped working at their end time already). @return int rows changed */
    public function expireDue(): int
    {
        $n = Entitlement::allTenants()->where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->update(['status' => 'expired']);
        $this->forget();

        return $n;
    }
}
