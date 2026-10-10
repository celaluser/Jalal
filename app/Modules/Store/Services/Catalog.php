<?php

namespace App\Modules\Store\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Menu\Services\ThemeLibrary;
use App\Modules\Store\Models\StoreItem;
use Illuminate\Support\Str;

/**
 * Everything a restaurant could unlock: each plan feature and each menu theme. The platform decides for every item whether it is
 * free for everyone, only comes with plans that include it, or can be bought (once, by the month or by the year).
 * What nobody changed keeps its default, which matches how the product behaved before the store existed.
 */
class Catalog
{
    public const MODES = ['free', 'plan', 'paid'];

    public const BILLINGS = ['one_time', 'monthly', 'yearly'];

    /** Features that were open to everyone before the store existed. */
    private const FREE_FEATURES = ['subdomain'];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $items = null;

    public function __construct(private readonly ThemeLibrary $themes, private readonly SettingsService $settings) {}

    public function forget(): void
    {
        $this->items = null;
    }

    /** @return array<string, array<string, mixed>> keyed by slug */
    public function all(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $rows = StoreItem::all()->keyBy('slug');
        $currency = strtoupper((string) $this->settings->get('billing.currency', 'USD'));
        $items = [];
        $sort = 0;

        foreach (Plan::FEATURES as $feature) {
            $slug = 'feature:'.$feature;
            $items[$slug] = $this->build($slug, 'feature', $feature, __('admin.plans.feature_'.$feature), $this->trans('store.feature_'.$feature.'_sum'), in_array($feature, self::FREE_FEATURES, true) ? 'free' : 'plan', $rows->get($slug), $currency, $sort++);
        }

        foreach ($this->themes->all() as $key => $theme) {
            $slug = 'theme:'.$key;
            $items[$slug] = $this->build($slug, 'theme', $key, $theme['name'], $this->trans('store.theme_summary'), 'free', $rows->get($slug), $currency, 1000 + $sort++);
        }

        uasort($items, fn ($a, $b) => [$a['sort'], $a['name']] <=> [$b['sort'], $b['name']]);

        return $this->items = $items;
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public function ofKind(string $kind): array
    {
        return array_filter($this->all(), fn ($i) => $i['kind'] === $kind);
    }

    /** @param array<string, mixed> $data validated by rules() */
    public function save(string $slug, array $data): void
    {
        StoreItem::updateOrCreate(['slug' => $slug], [
            'mode' => $data['mode'], 'billing' => $data['billing'], 'price' => round((float) $data['price'], 2),
            'currency_code' => ($data['currency_code'] ?? '') !== '' ? strtoupper($data['currency_code']) : null,
            'trial_days' => (int) ($data['trial_days'] ?? 0),
            'name' => $this->clean($data['name'] ?? null, 80), 'summary' => $this->clean($data['summary'] ?? null, 200), 'description' => $this->clean($data['description'] ?? null, 4000),
            'visible' => ! empty($data['visible']), 'sort' => (int) ($data['sort'] ?? 0),
        ]);
        $this->forget();
    }

    /** Back to the defaults. */
    public function reset(string $slug): void
    {
        StoreItem::where('slug', $slug)->delete();
        $this->forget();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:'.implode(',', self::MODES)],
            'billing' => ['required', 'in:'.implode(',', self::BILLINGS)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'name' => ['nullable', 'string', 'max:80'], 'summary' => ['nullable', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:4000'],
            'visible' => ['nullable', 'boolean'], 'sort' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ];
    }

    /** A paid item that can really be bought. */
    public function purchasable(array $item): bool
    {
        return $item['mode'] === 'paid' && $item['price'] > 0;
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function build(string $slug, string $kind, string $key, string $name, string $summary, string $defaultMode, ?StoreItem $row, string $currency, int $sort): array
    {
        $mode = $row && in_array($row->mode, self::MODES, true) ? $row->mode : $defaultMode;

        return [
            'slug' => $slug, 'kind' => $kind, 'key' => $key,
            'name' => $row?->name ?: $name, 'summary' => $row?->summary ?: $summary, 'description' => (string) $row?->description,
            'default_name' => $name, 'default_mode' => $defaultMode, 'customised' => $row !== null,
            'mode' => $mode,
            'billing' => $row && in_array($row->billing, self::BILLINGS, true) ? $row->billing : 'monthly',
            'price' => (float) ($row->price ?? 0),
            'currency' => strtoupper((string) ($row?->currency_code ?: $currency)),
            'trial_days' => (int) ($row->trial_days ?? 0),
            'visible' => $row ? (bool) $row->visible : true,
            'sort' => $row ? (int) $row->sort : $sort,
        ];
    }

    private function trans(string $key): string
    {
        return trans()->has($key) ? __($key) : '';
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($value, $max, '');
    }
}
