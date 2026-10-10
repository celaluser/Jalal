<?php

namespace App\Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Branches\Models\Branch;
use App\Modules\Menu\Support\Schedule;
use App\Modules\Orders\Models\Order;
use App\Modules\Tables\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Owner screens for the locations of a restaurant. */
class BranchController extends Controller
{
    public function index(Request $request, LimitGuard $limits): View
    {
        $restaurant = $request->user()->restaurant;
        $limit = $limits->limit($restaurant, 'branches');

        return view('branches::branches.index', [
            'branches' => Branch::orderBy('sort')->orderBy('id')->get(),
            'tables' => DiningTable::selectRaw('branch_id, count(*) as c')->groupBy('branch_id')->pluck('c', 'branch_id'),
            'limit' => $limit, 'canAdd' => $limit === null || Branch::count() < $limit,
        ]);
    }

    public function create(): View
    {
        return view('branches::branches.form', ['branch' => new Branch(['is_active' => true])]);
    }

    public function store(Request $request, LimitGuard $limits): RedirectResponse
    {
        $limit = $limits->limit($request->user()->restaurant, 'branches');

        if ($limit !== null && Branch::count() >= $limit) {
            return back()->withInput()->withErrors(['limit' => __('branches.limit_reached')]);
        }

        $data = $this->validated($request);
        Branch::create($data + ['slug' => $this->slug($data['name']), 'sort' => (int) Branch::max('sort') + 1]);

        return redirect()->route('branches.index')->with('status', __('branches.created'));
    }

    public function edit(Branch $branch): View
    {
        return view('branches::branches.form', ['branch' => $branch]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request));

        return redirect()->route('branches.index')->with('status', __('admin.saved'));
    }

    /** Deleting keeps tables, staff and past orders: they simply stop belonging to a branch. */
    public function destroy(Branch $branch): RedirectResponse
    {
        DiningTable::where('branch_id', $branch->id)->update(['branch_id' => null]);
        User::where('branch_id', $branch->id)->update(['branch_id' => null]);
        Order::where('branch_id', $branch->id)->update(['branch_id' => null]);
        $branch->delete();

        return redirect()->route('branches.index')->with('status', __('branches.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'schedule' => ['nullable', 'array'],
        ]);

        return [
            'name' => trim($data['name']), 'address' => $data['address'] ?? null, 'city' => $data['city'] ?? null,
            'phone' => $data['phone'] ?? null, 'schedule' => Schedule::fromInput($request->input('schedule')),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'branch';
        $slug = $base;

        for ($i = 2; Branch::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
