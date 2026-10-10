<?php

namespace App\Modules\Menu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Menu\Models\Option;
use App\Modules\Menu\Models\OptionGroup;
use App\Modules\Menu\Services\MenuService;
use App\Modules\Storefront\Services\MenuCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Option groups ("Size", "Extras") and their options, edited together on one screen. */
class OptionGroupController extends Controller
{
    public function __construct(private readonly MenuService $menu) {}

    public function index(): View
    {
        return view('menu::options.index', ['groups' => OptionGroup::withCount('products')->with('options')->orderBy('sort')->orderBy('id')->get()]);
    }

    public function create(Request $request): View
    {
        return view('menu::options.form', ['group' => new OptionGroup(['type' => 'single']), 'locales' => $request->user()->restaurant->menuLocales(), 'restaurant' => $request->user()->restaurant]);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = DB::transaction(function () use ($request) {
            $group = OptionGroup::create($this->groupFields($request) + ['sort' => $this->menu->nextSort(OptionGroup::class)]);
            $this->syncOptions($group, $request);

            return $group;
        });

        return redirect()->route('menu.option-groups.edit', $group)->with('status', __('menu.group_created'));
    }

    public function edit(Request $request, OptionGroup $optionGroup): View
    {
        return view('menu::options.form', ['group' => $optionGroup->load('options'), 'locales' => $request->user()->restaurant->menuLocales(), 'restaurant' => $request->user()->restaurant]);
    }

    public function update(Request $request, OptionGroup $optionGroup): RedirectResponse
    {
        DB::transaction(function () use ($request, $optionGroup) {
            $optionGroup->update($this->groupFields($request));
            $this->syncOptions($optionGroup, $request);
        });

        return redirect()->route('menu.option-groups.edit', $optionGroup)->with('status', __('admin.saved'));
    }

    public function destroy(OptionGroup $optionGroup): RedirectResponse
    {
        DB::transaction(function () use ($optionGroup) {
            $optionGroup->products()->detach();
            $optionGroup->options()->delete();
            $optionGroup->delete();
        });

        return redirect()->route('menu.option-groups.index')->with('status', __('menu.group_deleted'));
    }

    /** @return array<string, mixed> */
    private function groupFields(Request $request): array
    {
        $locales = $request->user()->restaurant->menuLocales();

        $request->validate([
            'name' => ['required', 'array'],
            "name.{$locales[0]}" => ['required', 'string', 'max:120'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(OptionGroup::TYPES)],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:50'],
            'options' => ['required', 'array', 'min:1', 'max:100'],
            'options.*.name' => ['required', 'array'],
            "options.*.name.{$locales[0]}" => ['required', 'string', 'max:120'],
            'options.*.name.*' => ['nullable', 'string', 'max:120'],
            'options.*.price_delta' => ['nullable', 'numeric', 'min:-9999999', 'max:9999999'],
            'options.*.id' => ['nullable', 'integer'],
        ]);

        $multiple = $request->input('type') === 'multiple';

        return [
            'name' => OptionGroup::cleanTranslations($request->input('name', []), $locales),
            'type' => $request->input('type'),
            'is_required' => $request->boolean('is_required'),
            'max_select' => $multiple && $request->filled('max_select') ? (int) $request->input('max_select') : null,
        ];
    }

    /** Update rows that keep their id, create new ones, delete the ones removed from the form. */
    private function syncOptions(OptionGroup $group, Request $request): void
    {
        $locales = $request->user()->restaurant->menuLocales();
        $existing = $group->options()->pluck('id')->all();
        $keep = [];
        $defaultTaken = false;

        foreach (array_values($request->input('options', [])) as $position => $row) {
            $isDefault = ! $defaultTaken && ! empty($row['is_default']);
            // A single-choice group preselects at most one option.
            $defaultTaken = $defaultTaken || ($group->type === 'single' && $isDefault);

            $fields = [
                'name' => Option::cleanTranslations($row['name'] ?? [], $locales),
                'price_delta' => $row['price_delta'] ?? 0,
                'is_available' => ! empty($row['is_available']),
                'is_default' => $group->type === 'single' ? $isDefault : ! empty($row['is_default']),
                'sort' => $position + 1,
            ];

            $id = (int) ($row['id'] ?? 0);

            if ($id && in_array($id, $existing, true)) {
                Option::whereKey($id)->update($fields);
                $keep[] = $id;
            } else {
                $keep[] = $group->options()->create($fields + ['restaurant_id' => $group->restaurant_id])->id;
            }
        }

        $group->options()->whereNotIn('id', $keep)->delete();
        MenuCache::bump($group->restaurant_id); // bulk delete fires no model events
    }
}
