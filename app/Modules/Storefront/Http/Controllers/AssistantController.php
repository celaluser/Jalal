<?php

namespace App\Modules\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Services\AiAssistant;
use App\Modules\Ai\Services\AiManager;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Marketing\Services\MarketingSettings;
use App\Modules\Menu\Services\MenuAvailability;
use App\Modules\Storefront\Services\MenuCache;
use App\Modules\Storefront\Services\MenuLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * "Ask about the menu": a guest question answered by the AI from the dishes that are on the menu right now.
 * The restaurant pays one AI credit per answer, so it is opt-in, rate limited, and capped per visitor per day.
 */
class AssistantController extends Controller
{
    private const DAILY_PER_VISITOR = 25;

    public function __construct(private readonly TenantContext $tenant, private readonly MenuLocale $locales) {}

    public function ask(Request $request, AiAssistant $ai, AiManager $manager, MenuCache $cache): JsonResponse
    {
        $restaurant = $this->tenant->get();
        $locale = $this->locales->resolve($request, $restaurant);
        app()->setLocale($locale);

        abort_unless(app(MarketingSettings::class)->get($restaurant, 'ai_assistant') && $manager->configured() && app(LimitGuard::class)->plan($restaurant) !== null, 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:300'],
            'history' => ['nullable', 'array', 'max:12'], 'history.*.role' => ['required', 'in:user,assistant'], 'history.*.text' => ['required', 'string', 'max:700'],
        ]);

        $visitor = sha1($request->ip().'|'.$request->userAgent());
        $key = "assistant.{$restaurant->id}.{$visitor}.".now()->toDateString();

        if (Cache::get($key, 0) >= self::DAILY_PER_VISITOR) {
            return response()->json(['message' => __('ai.error_rate_limited')], 429);
        }

        $tree = app(MenuAvailability::class)->filter($cache->tree($restaurant, $locale), $restaurant);
        $allergens = config('menu.allergens');
        $menu = collect($tree)->flatMap(fn ($c) => collect($c['products'])->where('available', true)->map(fn ($p) => [
            'id' => $p['id'], 'name' => $p['name'], 'category' => $c['name'], 'price' => $restaurant->money($p['price']), 'description' => mb_substr((string) $p['description'], 0, 160),
            'allergens' => array_values(array_intersect($allergens, $p['allergens'] ?? [])), 'dietary' => $p['dietary'] ?? [], 'spice' => $p['spice'] ?? 0,
        ]))->take(150)->values()->all();

        try {
            $out = $ai->assist($restaurant, $menu, $data['history'] ?? [], $data['message'], $locale);
        } catch (AiException $e) {
            // A guest does not need to know why: the assistant is simply not available.
            return response()->json(['message' => __('ai.assistant_error')], 503);
        }

        Cache::put($key, Cache::get($key, 0) + 1, 90000);
        $names = collect($menu)->keyBy('id');

        return response()->json(['answer' => $out['answer'], 'products' => collect($out['products'])->map(fn ($id) => ['id' => $id, 'name' => $names[$id]['name'] ?? ''])->values()->all()]);
    }
}
