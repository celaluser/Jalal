<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The restaurant's own discount codes for guests (not the platform's subscription coupons). */
class PromoController extends Controller
{
    public function index(Request $request): View
    {
        return view('marketing::promos.index', [
            'promos' => PromoCode::with('customer')->orderByDesc('id')->paginate(25), 'restaurant' => $request->user()->restaurant,
        ]);
    }

    public function create(Request $request): View
    {
        return view('marketing::promos.form', ['promo' => null, 'restaurant' => $request->user()->restaurant]);
    }

    public function store(Request $request): RedirectResponse
    {
        PromoCode::create($this->payload($request, null));

        return redirect()->route('promos.index')->with('status', __('marketing.promo_saved'));
    }

    public function edit(Request $request, int $promo): View
    {
        $model = $this->find($promo);
        abort_if($model->isLoyaltyReward(), 403);

        return view('marketing::promos.form', ['promo' => $model, 'restaurant' => $request->user()->restaurant]);
    }

    public function update(Request $request, int $promo): RedirectResponse
    {
        $model = $this->find($promo);
        $model->update($this->payload($request, $model));

        return redirect()->route('promos.index')->with('status', __('marketing.promo_saved'));
    }

    public function toggle(int $promo): RedirectResponse
    {
        $model = $this->find($promo);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('status', __('admin.saved'));
    }

    public function destroy(int $promo): RedirectResponse
    {
        $this->find($promo)->delete();

        return redirect()->route('promos.index')->with('status', __('marketing.promo_deleted'));
    }

    /** Loyalty rewards are made by the system for one person: they can be switched off or deleted, not edited. */
    private function find(int $id): PromoCode
    {
        return PromoCode::findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, ?PromoCode $existing): array
    {
        abort_if($existing?->isLoyaltyReward() && $request->isMethod('PUT'), 403);

        $request->merge(['code' => PromoCode::normalize($request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', Rule::unique('promo_codes', 'code')->where('restaurant_id', $request->user()->restaurant_id)->ignore($existing?->id)],
            'description' => ['nullable', 'string', 'max:160'],
            'type' => ['required', Rule::in([PromoCode::PERCENT, PromoCode::FIXED])],
            'value' => ['required', 'numeric', 'min:0.01', 'max:'.($request->input('type') === PromoCode::PERCENT ? 100 : 99999)],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'code' => $data['code'], 'description' => $data['description'] ?? null, 'type' => $data['type'],
            'value' => $data['type'] === PromoCode::PERCENT ? (int) round((float) $data['value']) : (int) round((float) $data['value'] * 100),
            'min_order_cents' => (int) round((float) ($data['min_order'] ?? 0) * 100),
            'max_uses' => $data['max_uses'] ?? null,
            'starts_at' => ! empty($data['starts_at']) ? $data['starts_at'] : null,
            'ends_at' => ! empty($data['ends_at']) ? Carbon::parse($data['ends_at'])->endOfDay() : null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
