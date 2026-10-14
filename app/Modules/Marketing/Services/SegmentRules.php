<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rules a saved segment can combine (all must hold). Every rule is optional; an empty rule set means everyone.
 * Only whitelisted keys are ever read, so a saved or submitted rule set cannot reach the query in any other way.
 */
class SegmentRules
{
    public const KEYS = ['min_orders', 'max_orders', 'min_spent', 'active_within_days', 'inactive_for_days', 'joined_within_days', 'birthday', 'tier'];

    public function __construct(private readonly Segments $segments) {}

    /** @param array<string, mixed> $input @return array<string, int|string> only valid, non-empty rules */
    public function clean(array $input): array
    {
        $out = [];

        foreach (['min_orders', 'max_orders', 'active_within_days', 'inactive_for_days', 'joined_within_days'] as $key) {
            if (isset($input[$key]) && $input[$key] !== '' && (int) $input[$key] >= 0) {
                $out[$key] = min(100000, (int) $input[$key]);
            }
        }

        if (isset($input['min_spent']) && $input['min_spent'] !== '' && (float) $input['min_spent'] > 0) {
            $out['min_spent'] = (int) round(min(10000000, (float) $input['min_spent']) * 100); // stored in cents
        }

        if (in_array($input['birthday'] ?? '', ['this_month', 'next_month'], true)) {
            $out['birthday'] = $input['birthday'];
        }

        if (in_array($input['tier'] ?? '', ['bronze', 'silver', 'gold'], true)) {
            $out['tier'] = $input['tier'];
        }

        return $out;
    }

    /** @param array<string, mixed> $rules */
    public function apply(Builder $query, array $rules, Restaurant $restaurant): Builder
    {
        $rules = $this->clean($this->forInput($rules));

        foreach (['min_orders' => ['orders_count', '>='], 'max_orders' => ['orders_count', '<=']] as $key => [$column, $op]) {
            if (isset($rules[$key])) {
                $query->where($column, $op, $rules[$key]);
            }
        }

        if (isset($rules['min_spent'])) {
            $query->where('total_cents', '>=', $rules['min_spent']);
        }

        if (isset($rules['active_within_days'])) {
            $query->where('last_order_at', '>=', now()->subDays($rules['active_within_days']));
        }

        if (isset($rules['inactive_for_days'])) {
            $query->where('last_order_at', '<', now()->subDays($rules['inactive_for_days']));
        }

        if (isset($rules['joined_within_days'])) {
            $query->where('created_at', '>=', now()->subDays($rules['joined_within_days']));
        }

        if (isset($rules['birthday'])) {
            $query->where('birth_month', $rules['birthday'] === 'this_month' ? (int) now()->month : (int) now()->addMonthNoOverflow()->month);
        }

        if (isset($rules['tier'])) {
            $s = app(MarketingSettings::class)->for($restaurant);
            [$silver, $gold] = [(int) $s['tier_silver'], (int) $s['tier_gold']];
            match ($rules['tier']) {
                'gold' => $query->where('orders_count', '>=', $gold),
                'silver' => $query->where('orders_count', '>=', $silver)->where('orders_count', '<', $gold),
                default => $query->where('orders_count', '<', $silver),
            };
        }

        return $query;
    }

    /** A saved rule set already holds spent in cents; clean() expects the form value (money), so convert back. */
    private function forInput(array $rules): array
    {
        if (isset($rules['min_spent'])) {
            $rules['min_spent'] = (float) $rules['min_spent'] / 100;
        }

        return $rules;
    }
}
