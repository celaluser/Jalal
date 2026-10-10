<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\PriceRule;
use App\Modules\Menu\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Happy hour rules: "20% off drinks, weekdays 16:00-18:00". */
class PriceRuleController extends Controller
{
    public function index(Request $request): View
    {
        return view('marketing::pricing.index', ['restaurant' => $request->user()->restaurant, 'rules' => PriceRule::orderByDesc('id')->get(), 'categories' => Category::all()->keyBy('id')]);
    }

    public function store(Request $request): RedirectResponse
    {
        PriceRule::create($this->validated($request));

        return back()->with('status', __('marketing.rule_saved'));
    }

    public function toggle(int $rule): RedirectResponse
    {
        $model = PriceRule::findOrFail($rule);
        $model->update(['is_active' => ! $model->is_active]);

        return back();
    }

    public function destroy(int $rule): RedirectResponse
    {
        PriceRule::findOrFail($rule)->delete();

        return back()->with('status', __('marketing.rule_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'percent' => ['required', 'integer', 'between:1,90'],
            'days' => ['nullable', 'array'], 'days.*' => ['integer', 'between:0,6'],
            'from_time' => ['required', 'date_format:H:i'], 'to_time' => ['required', 'date_format:H:i'],
            'category_id' => ['nullable', 'integer'],
        ]);
        // A category id must belong to this restaurant (the scoped query returns nothing for another one).
        $data['category_id'] = ! empty($data['category_id']) && Category::whereKey($data['category_id'])->exists() ? (int) $data['category_id'] : null;
        $data['days'] = ! empty($data['days']) && count($data['days']) < 7 ? array_values(array_map('intval', $data['days'])) : null;

        return $data;
    }
}
