<?php

namespace App\Modules\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Jobs\TranslateMenu;
use App\Modules\Ai\Services\AiAssistant;
use App\Modules\Ai\Services\AiCredits;
use App\Modules\Ai\Services\AiManager;
use App\Modules\Analytics\Services\ReportService;
use App\Modules\Marketing\Models\Review;
use App\Modules\Menu\Services\MenuImporter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Smalot\PdfParser\Parser;

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
            'cost' => $this->credits->cost('menu_import'), 'photoCost' => $this->credits->cost('photo_import'), 'maxChars' => AiAssistant::IMPORT_MAX_CHARS,
        ]);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:10', 'max:'.(AiAssistant::IMPORT_MAX_CHARS * 2)]]);
        $restaurant = $request->user()->restaurant;

        return $this->respond($request, fn () => $this->ai->importMenu($restaurant, $data['text'], $restaurant->locale, $request->user()));
    }

    /** A photo or screenshot of a menu, read by a model that can see. */
    public function importPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        $file = $request->file('photo');
        $mime = (string) (@getimagesize($file->getRealPath())['mime'] ?? '');

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->json(['error' => 'bad_file', 'message' => __('ai.error_bad_file')], 422);
        }

        $restaurant = $request->user()->restaurant;
        $image = ['mime' => $mime, 'base64' => base64_encode((string) file_get_contents($file->getRealPath()))];

        return $this->respond($request, fn () => $this->ai->importMenuFromImage($restaurant, $image, $restaurant->locale, $request->user()));
    }

    /** A PDF menu: its text is read on this server, then handled like pasted text. Scanned PDFs have no text; use the photo import for those. */
    public function importPdf(Request $request): JsonResponse
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:8192']]);

        try {
            $text = (new Parser)->parseFile($request->file('pdf')->getRealPath())->getText();
        } catch (\Throwable) {
            return response()->json(['error' => 'bad_file', 'message' => __('ai.error_bad_file')], 422);
        }

        if (mb_strlen(trim((string) $text)) < 10) {
            return response()->json(['error' => 'pdf_no_text', 'message' => __('ai.error_pdf_no_text')], 422);
        }

        $restaurant = $request->user()->restaurant;

        return $this->respond($request, fn () => $this->ai->importMenu($restaurant, (string) $text, $restaurant->locale, $request->user()));
    }

    /** A drafted reply to a review. Nothing is saved or sent: the owner reads it, edits it and presses the normal reply button. */
    public function reviewReply(Request $request, int $review): JsonResponse
    {
        $row = Review::findOrFail($review);
        $restaurant = $request->user()->restaurant;

        return $this->respond($request, fn () => ['reply' => $this->ai->replyToReview($restaurant, (int) $row->rating, $row->comment, $restaurant->locale, $request->user())]);
    }

    /** Advice from the numbers of a period. */
    public function insights(Request $request, ReportService $reports): JsonResponse
    {
        $restaurant = $request->user()->restaurant;
        $period = $reports->period($restaurant, $request->query('range'), $request->query('from'), $request->query('to'));
        $r = $reports->build($restaurant, $period, true);
        $facts = [
            'days' => $period['days'], 'orders' => $r['orders'], 'revenue' => round($r['revenue'] / 100, 2), 'average_order' => round($r['average'] / 100, 2), 'cancel_rate_percent' => $r['cancel_rate'], 'discounts' => round($r['discounts'] / 100, 2),
            'change_percent' => $r['delta'], 'top_dishes' => collect($r['top'])->take(8)->map(fn ($t) => ['name' => $t['name'] ?? '', 'sold' => $t['qty'] ?? 0])->all(),
            'by_type' => $r['by_type'], 'by_payment' => $r['by_payment'], 'minutes_to_ready' => $r['ready_minutes'],
        ];

        return $this->respond($request, fn () => ['tips' => $this->ai->insights($restaurant, $facts, $restaurant->locale, $request->user())]);
    }

    /** Translate the whole menu in the background (only empty fields, never locked languages). */
    public function bulkTranslate(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurant;

        if (! $this->manager->configured()) {
            return response()->json(['error' => 'not_configured', 'message' => __('ai.error_not_configured')], 503);
        }

        if ((Cache::get(TranslateMenu::key($restaurant->id))['status'] ?? null) === 'running') {
            return response()->json(['error' => 'busy', 'message' => __('ai.bulk_running')], 409);
        }

        Cache::put(TranslateMenu::key($restaurant->id), ['status' => 'running', 'total' => 0, 'done' => 0, 'translated' => 0, 'stopped' => null], 3600);
        TranslateMenu::dispatch($restaurant->id, $request->user()->id);

        return response()->json(['started' => true], 202);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        return response()->json(Cache::get(TranslateMenu::key($request->user()->restaurant_id), ['status' => 'idle']))->header('Cache-Control', 'no-store');
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
