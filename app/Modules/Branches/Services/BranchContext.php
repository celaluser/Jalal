<?php

namespace App\Modules\Branches\Services;

use App\Models\User;
use App\Modules\Branches\Models\Branch;
use Illuminate\Support\Collection;

/**
 * Which branch the signed-in staff member is looking at. People assigned to one branch are fixed to it; owners and
 * managers can switch between branches or look at all of them (null). Restaurants without branches always give null.
 */
class BranchContext
{
    public const SESSION_KEY = 'branch_id';

    /** @return Collection<int, Branch> branches this user may work in */
    public function available(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user || ! $user->restaurant_id) {
            return collect();
        }

        $all = Branch::where('is_active', true)->orderBy('sort')->orderBy('id')->get();

        return $user->branch_id ? $all->where('id', $user->branch_id)->values() : $all;
    }

    /** True when this restaurant has any branches at all. */
    public function enabled(): bool
    {
        return Branch::query()->exists();
    }

    /** The chosen branch, or null for "all branches" (and for restaurants with no branches). */
    public function current(?User $user = null): ?Branch
    {
        $user ??= auth()->user();
        $list = $this->available($user);

        if ($list->isEmpty()) {
            return null;
        }

        if ($user->branch_id) {
            return $list->first();
        }

        $id = (int) session(self::SESSION_KEY, 0);

        return $id ? $list->firstWhere('id', $id) : null;
    }

    public function currentId(?User $user = null): ?int
    {
        return $this->current($user)?->id;
    }

    public function switchTo(?int $id, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user->branch_id) {
            return false; // fixed to their branch
        }

        if ($id !== null && ! $this->available($user)->contains('id', $id)) {
            return false;
        }

        session([self::SESSION_KEY => $id ?: 0]);

        return true;
    }
}
