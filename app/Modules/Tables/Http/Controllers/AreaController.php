<?php

namespace App\Modules\Tables\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Tables\Models\Area;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Area::create($this->validated($request) + ['sort' => (int) Area::max('sort') + 1]);

        return back()->with('status', __('tables.area_created'));
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $area->update($this->validated($request, $area));

        return back()->with('status', __('admin.saved'));
    }

    /** Tables of a deleted area are kept, just without an area. */
    public function destroy(Area $area): RedirectResponse
    {
        DiningTable::where('area_id', $area->id)->update(['area_id' => null]);
        $area->delete();

        return redirect()->route('tables.index')->with('status', __('tables.area_deleted'));
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Area $area = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('areas', 'name')->where('restaurant_id', app(TenantContext::class)->id())->ignore($area?->id)],
        ]);

        return ['name' => trim($data['name'])];
    }
}
