<?php

namespace App\Modules\Orders\Services;

use App\Models\User;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Orders\Models\OrderPayment;
use App\Modules\Tenancy\Models\Restaurant;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Splits the tips collected in a period between the staff by weight (a share each person counts for; 0 = not in the pool),
 * after an optional percentage the house keeps. Cents are handed out by largest remainder so the parts always add up.
 */
class TipPool
{
    public function __construct(private readonly SettingsService $settings) {}

    /** Tips paid in the period, in cents. */
    public function collected(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) OrderPayment::where('status', OrderPayment::PAID)->where('tip_cents', '>', 0)->whereBetween('paid_at', [$from, $to])->sum('tip_cents');
    }

    /** @return array<int, float> user id => weight (staff without a saved value count as 1) */
    public function weights(Restaurant $restaurant): array
    {
        $saved = json_decode((string) $this->settings->get('tips.weights', '{}', $restaurant->id), true);
        $saved = is_array($saved) ? $saved : [];
        $weights = [];

        foreach ($this->staff($restaurant) as $user) {
            $weights[$user->id] = array_key_exists($user->id, $saved) ? max(0.0, min(100.0, (float) $saved[$user->id])) : 1.0;
        }

        return $weights;
    }

    public function housePercent(Restaurant $restaurant): float
    {
        return max(0.0, min(100.0, (float) $this->settings->get('tips.house_percent', '0', $restaurant->id)));
    }

    /** @param array<int|string, mixed> $weights user id => weight */
    public function save(Restaurant $restaurant, array $weights, float $housePercent): void
    {
        $ids = $this->staff($restaurant)->pluck('id')->all();
        $clean = [];

        foreach ($weights as $id => $w) {
            if (in_array((int) $id, $ids, true)) {
                $clean[(int) $id] = max(0.0, min(100.0, round((float) $w, 2)));
            }
        }

        $this->settings->set('tips.weights', json_encode($clean), $restaurant->id);
        $this->settings->set('tips.house_percent', (string) max(0.0, min(100.0, round($housePercent, 2))), $restaurant->id);
    }

    /** @return Collection<int, User> */
    public function staff(Restaurant $restaurant)
    {
        return User::where('restaurant_id', $restaurant->id)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * @param  array<int, float>  $weights
     * @return array{total: int, house: int, pool: int, shares: array<int, int>}
     */
    public function split(int $total, float $housePercent, array $weights): array
    {
        $house = (int) round($total * $housePercent / 100);
        $pool = max(0, $total - $house);
        $weights = array_filter($weights, fn ($w) => $w > 0);
        $sum = array_sum($weights);

        if ($sum <= 0) {
            return ['total' => $total, 'house' => $total, 'pool' => 0, 'shares' => []]; // nobody to share with: nothing is lost, the house holds it
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $id => $w) {
            $exact = $pool * $w / $sum;
            $shares[$id] = (int) floor($exact);
            $remainders[$id] = $exact - $shares[$id];
        }

        arsort($remainders);

        for ($left = $pool - array_sum($shares), $ids = array_keys($remainders); $left > 0; $left--) {
            $shares[array_shift($ids)]++;
        }

        return ['total' => $total, 'house' => $house, 'pool' => $pool, 'shares' => $shares];
    }
}
