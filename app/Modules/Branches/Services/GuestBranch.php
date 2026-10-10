<?php

namespace App\Modules\Branches\Services;

use App\Modules\Branches\Models\Branch;
use App\Modules\Tables\Models\DiningTable;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Which branch a guest is ordering from. Order of precedence: the scanned table's branch, a ?branch=slug link,
 * the branch remembered in the session, the only active branch. With two or more and no choice the guest is asked.
 */
class GuestBranch
{
    private function key(Restaurant $restaurant): string
    {
        return "branch.{$restaurant->id}";
    }

    /** @return Collection<int, Branch> */
    public function active(): Collection
    {
        return Branch::where('is_active', true)->orderBy('sort')->orderBy('id')->get();
    }

    public function resolve(Request $request, Restaurant $restaurant, ?DiningTable $table = null): ?Branch
    {
        $branches = $this->active();

        if ($branches->isEmpty()) {
            return null;
        }

        if ($table?->branch_id && ($branch = $branches->firstWhere('id', $table->branch_id))) {
            $request->session()->put($this->key($restaurant), $branch->id);

            return $branch;
        }

        if (($slug = $request->query('branch')) && ($branch = $branches->firstWhere('slug', (string) $slug))) {
            $request->session()->put($this->key($restaurant), $branch->id);

            return $branch;
        }

        if ($branch = $branches->firstWhere('id', (int) $request->session()->get($this->key($restaurant), 0))) {
            return $branch;
        }

        return $branches->count() === 1 ? $branches->first() : null;
    }

    /** The branch id for a JSON call (quote / order), without the ?branch handling. */
    public function id(Request $request, Restaurant $restaurant): ?int
    {
        return $this->resolve($request, $restaurant, $this->tableOf($request, $restaurant))?->id;
    }

    private function tableOf(Request $request, Restaurant $restaurant): ?DiningTable
    {
        $id = $request->session()->get("table.{$restaurant->id}");

        return $id ? DiningTable::find($id) : null;
    }
}
