<?php

namespace App\Modules\Surveys\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Menu\Services\ThemeRegistry;
use App\Modules\Orders\Models\Order;
use App\Modules\Storefront\Services\MenuLocale;
use App\Modules\Surveys\Models\Survey;
use App\Modules\Surveys\Models\SurveyResponse;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** A guest answers a survey from its link (or from the order page). One answer per order. */
class GuestSurveyController extends Controller
{
    public function __construct(private readonly TenantContext $tenant, private readonly MenuLocale $locales, private readonly ThemeRegistry $themes) {}

    public function show(Request $request): View
    {
        [$restaurant, $survey] = $this->open($request);
        $order = $this->order($request);

        return $this->page($request, $restaurant, $survey, ['done' => $order !== null && SurveyResponse::where('survey_id', $survey->id)->where('order_id', $order->id)->exists() || $request->boolean('thanks'), 'orderToken' => $order?->token]);
    }

    public function submit(Request $request): RedirectResponse
    {
        [, $survey] = $this->open($request);
        $order = $this->order($request);
        $input = (array) $request->input('a', []);
        $answers = [];
        $errors = [];

        foreach ($survey->questions as $q) {
            $v = $input[$q['id']] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            $empty = $v === null || $v === '';

            if ($empty) {
                if ($q['required']) {
                    $errors['a.'.$q['id']] = __('surveys.required');
                }

                continue;
            }

            $ok = match ($q['type']) {
                'rating' => is_numeric($v) && (int) $v >= 1 && (int) $v <= 5,
                'nps' => is_numeric($v) && (int) $v >= 0 && (int) $v <= 10,
                'choice' => is_string($v) && in_array($v, $q['options'], true),
                default => is_string($v),
            };

            if (! $ok) {
                $errors['a.'.$q['id']] = __('surveys.invalid');

                continue;
            }

            $answers[$q['id']] = in_array($q['type'], ['rating', 'nps'], true) ? (int) $v : mb_substr((string) $v, 0, 1000);
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        if ($answers === []) {
            return back()->withErrors(['survey' => __('surveys.empty_form')]);
        }

        $stored = DB::transaction(function () use ($survey, $order, $answers) {
            if ($order !== null && SurveyResponse::where('survey_id', $survey->id)->where('order_id', $order->id)->exists()) {
                return false;
            }

            SurveyResponse::create(['survey_id' => $survey->id, 'order_id' => $order?->id, 'answers' => $answers, 'locale' => app()->getLocale()]);
            Survey::whereKey($survey->id)->increment('responses_count');

            return true;
        });

        $base = $this->base($request).'/survey/'.$survey->slug.'?thanks=1';

        return redirect($base.($order ? '&o='.$order->token : ''))->with('survey_new', $stored);
    }

    /** @return array{0: Restaurant, 1: Survey} */
    /** The restaurant's route has two parameters ({restaurant} and {slug}); controller arguments would be filled by position, so the slug is read by name. */
    private function open(Request $request): array
    {
        $slug = (string) $request->route('slug');
        $restaurant = $this->tenant->get();
        app()->setLocale($this->locales->resolve($request, $restaurant));
        $survey = Survey::where('slug', $slug)->where('is_active', true)->first();
        abort_if($survey === null, 404);

        return [$restaurant, $survey];
    }

    private function order(Request $request): ?Order
    {
        $token = scalar_string($request->input('o', $request->query('o', '')));

        return preg_match('/^[A-Za-z0-9]{24}$/', $token) === 1 ? Order::where('token', $token)->first() : null;
    }

    private function base(Request $request): string
    {
        return $request->route('restaurant') !== null ? '/'.config('tenancy.path_prefix').'/'.$request->route('restaurant') : '';
    }

    /** @param array<string, mixed> $extra */
    private function page(Request $request, $restaurant, Survey $survey, array $extra): View
    {
        return view('surveys::guest.survey', $extra + ['survey' => $survey, 'restaurant' => $restaurant, 'locale' => app()->getLocale(), 'dir' => $this->locales->isRtl(app()->getLocale()) ? 'rtl' : 'ltr', 'themeCss' => $this->themes->css($restaurant), 'base' => $this->base($request)]);
    }
}
