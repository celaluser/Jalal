<?php

namespace App\Modules\Ai\Jobs;

use App\Models\User;
use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Services\AiAssistant;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Models\Category;
use App\Modules\Menu\Models\Product;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Translates every dish and category into the menu languages where text is missing. It only FILLS EMPTY fields and never
 * touches a language a person locked. It stops when the monthly AI credits run out, and reports progress in the cache.
 */
class TranslateMenu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $restaurantId, public readonly ?int $userId = null) {}

    public static function key(int $restaurantId): string
    {
        return "ai.bulk.{$restaurantId}";
    }

    public function handle(AiAssistant $ai, TenantContext $tenant): void
    {
        $restaurant = Restaurant::find($this->restaurantId);

        if (! $restaurant) {
            return;
        }

        $by = $this->userId ? User::find($this->userId) : null;
        $key = self::key($restaurant->id);

        $tenant->runAs($restaurant, function () use ($restaurant, $ai, $by, $key) {
            $from = $restaurant->locale;
            $locales = array_values(array_diff($restaurant->menuLocales(), [$from]));
            $items = collect([...Category::all()->all(), ...Product::all()->all()]);
            $state = ['status' => 'running', 'total' => $items->count(), 'done' => 0, 'translated' => 0, 'stopped' => null];
            Cache::put($key, $state, 3600);

            foreach ($items as $item) {
                $locked = (array) $item->locked_locales;
                $fields = ['name' => $item->name, 'description' => $item->description];
                $missing = [];

                foreach ($locales as $code) {
                    foreach ($fields as $field => $values) {
                        if (! in_array($code, $locked, true) && ! empty($values[$from]) && empty($values[$code])) {
                            $missing[$code][] = $field;
                        }
                    }
                }

                if ($missing) {
                    $targets = array_keys($missing);
                    $texts = array_filter(['name' => $item->name[$from] ?? '', 'description' => $item->description[$from] ?? '']);

                    try {
                        $result = $ai->translate($restaurant, $texts, $from, $targets, $by);
                    } catch (AiException $e) {
                        if (in_array($e->reason, ['no_credits', 'not_configured', 'rate_limited'], true)) {
                            $state['stopped'] = $e->reason;
                            break;
                        }

                        $result = []; // one bad answer does not stop the rest
                    }

                    foreach ($result as $code => $fieldsDone) {
                        foreach ($fieldsDone as $field => $text) {
                            if (in_array($field, $missing[$code] ?? [], true)) {
                                $values = (array) $item->{$field};
                                $values[$code] = $text;
                                $item->{$field} = $values;
                                $state['translated']++;
                            }
                        }
                    }

                    $item->save();
                }

                $state['done']++;
                Cache::put($key, $state, 3600);
            }

            Cache::put($key, ['status' => $state['stopped'] ? 'stopped' : 'finished'] + $state, 3600);
        });
    }
}
