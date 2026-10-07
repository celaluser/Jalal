<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Services\AiAssistant;
use App\Modules\Ai\Services\AiCredits;
use App\Modules\Ai\Services\AiManager;
use App\Modules\Menu\Services\MenuImporter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** JSON endpoints behind the "write with AI" buttons of the menu editor, and the menu import page. */
class AiController extends Controller
{
    public function __construct(private readonly AiAssistant $ai, private readonly AiCredits $credits, private readonly AiManager $manager) {}

    public function describe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'category' => ['nullable', 'string', 'max:120'],
            'hints' => ['nullable', 'string', 'max:500'], 'tone' => ['nullable', Rule::in(AiAssistant::TONES)],
        ]);
        $restaurant = $request->user()->restaurant;

        return $this->respond($request, fn () => ['description' => $this->ai->describe($restaurant, $data['name'], $data['category'] ?? null, $data['hints'] ?? null, $data['tone'] ?? 'appetizing', $restaurant->locale, $request->user())]);
    }

    public function translate(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurant;
        $data = $request->validate([
            'texts' => ['required', 'array', 'min:1', 'max:4'], 'texts.*' => ['nullable', 'string', 'max:1000'],
            'targets' => ['required', 'array', 'min:1', 'max:8'], 'targets.*' => ['string', Rule::in($restaurant->menuLocales())],
        ]);

        return $this->respond($request, fn () => ['translations' => $this->ai->translate($restaurant, $data['texts'], $restaurant->locale, $data['targets'], $request->user())]);
    }

    public function tags(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'], 'hints' => ['nullable', 'string', 'max:500']]);
        $restaurant = $request->user()->restaurant;

        return $this->respond($request, fn () => $this->ai->tags($restaurant, $data['name'], $data['description'] ?? null, $data['hints'] ?? null, $request->user()));
    }

    public function importPage(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('ai::import', [
            'restaurant' => $restaurant, 'enabled' => $this->manager->configured(), 'credits' => $this->credits->summary($restaurant),
            'cost' => $this->credits->cost('menu_import'), 'maxChars' => AiAssistant::IMPORT_MAX_CHARS,
        ]);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:10', 'max:'.(AiAssistant::IMPORT_MAX_CHARS * 2)]]);
        $restaurant = $request->user()->restaurant;

        return $this->respond($request, fn () => $this->ai->importMenu($restaurant, $data['text'], $restaurant->locale, $request->user()));
    }

    /** Create what the owner approved in the preview. No AI call, no credits. */
    public function importCommit(Request $request, MenuImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1', 'max:'.AiAssistant::IMPORT_MAX_CATEGORIES],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.items' => ['required', 'array', 'min:1', 'max:'.AiAssistant::IMPORT_MAX_ITEMS],
            'categories.*.items.*.name' => ['required', 'string', 'max:160'],
            'categories.*.items.*.description' => ['nullable', 'string', 'max:500'],
            'categories.*.items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);

        // The preview is editable in the browser, so names are cleaned again here.
        $clean = array_map(fn ($c) => ['name' => strip_tags($c['name']), 'items' => array_map(fn ($i) => ['name' => strip_tags($i['name']), 'description' => strip_tags((string) ($i['description'] ?? '')), 'price' => $i['price'] ?? null], $c['items'])], $data['categories']);

        try {
            $result = $importer->import($request->user()->restaurant, $clean);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage(), 'message' => __('ai.error_'.$e->getMessage())], 422);
        }

        return response()->json($result + ['url' => route('menu.index')], 201);
    }

    /** @param Closure(): array<string, mixed> $work */
    private function respond(Request $request, Closure $work): JsonResponse
    {
        $restaurant = $request->user()->restaurant;

        try {
            $result = $work();
        } catch (AiException $e) {
            if (in_array($e->reason, ['provider_error', 'bad_response'], true)) {
                Log::warning('AI request failed', ['restaurant' => $restaurant->id, 'reason' => $e->getMessage()]);
            }

            return response()->json(['error' => $e->reason, 'message' => __('ai.error_'.$e->reason)], match ($e->reason) {
                'no_credits' => 402, 'not_configured' => 503, 'rate_limited' => 429, default => 502,
            });
        }

        return response()->json($result + ['credits' => $this->credits->summary($restaurant)]);
    }
}
