<?php

namespace App\Modules\Team\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Branches\Models\Branch;
use App\Modules\Team\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** Staff of the restaurant: who can sign in and what they may do. */
class TeamController extends Controller
{
    public function __construct(private readonly TeamService $team) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $users = $restaurant->users()->orderByRaw('id = ? desc', [$restaurant->owner_id])->orderBy('name')->get();

        return view('team::team.index', [
            'restaurant' => $restaurant, 'users' => $users, 'team' => $this->team,
            'remaining' => $this->team->remaining($restaurant), 'canManage' => $request->user()->can('staff.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $restaurant = $request->user()->restaurant;

        return view('team::team.form', ['member' => null, 'roles' => $this->team->roles($restaurant), 'remaining' => $this->team->remaining($restaurant), 'branches' => Branch::orderBy('sort')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in($this->team->roles($restaurant))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
        ]);

        try {
            $member = $this->team->invite($restaurant, $data['name'], $data['email'], $data['role'], $request->user());
            $member->forceFill(['branch_id' => $data['branch_id'] ?? null])->save();
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['team' => __('team.error_'.$e->getMessage())]);
        }

        return redirect()->route('team.index')->with('status', __('team.invited', ['email' => $data['email']]));
    }

    public function edit(Request $request, int $user): View
    {
        $restaurant = $request->user()->restaurant;

        return view('team::team.form', ['member' => $this->find($request, $user), 'roles' => $this->team->roles($restaurant), 'team' => $this->team, 'remaining' => null, 'branches' => Branch::orderBy('sort')->get()]);
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $member = $this->find($request, $user);
        $data = $request->validate([
            'role' => ['required', 'string', Rule::in($this->team->roles($restaurant))],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('restaurant_id', $restaurant->id)],
        ]);
        $member->forceFill(['branch_id' => $data['branch_id'] ?? null])->save();

        return $this->attempt(fn () => $this->team->changeRole($restaurant, $member, $data['role'], $request->user()), __('admin.saved'), route('team.index'));
    }

    public function toggle(Request $request, int $user): RedirectResponse
    {
        $member = $this->find($request, $user);
        $disable = $member->disabled_at === null;

        return $this->attempt(fn () => $this->team->setDisabled($member, $disable, $request->user()), $disable ? __('team.disabled_done') : __('team.enabled_done'));
    }

    public function resend(Request $request, int $user): RedirectResponse
    {
        $member = $this->find($request, $user);
        abort_unless($member->invited_at !== null, 404);

        $this->team->sendInvitation($member, $request->user());

        return back()->with('status', __('team.resent', ['email' => $member->email]));
    }

    public function destroy(Request $request, int $user): RedirectResponse
    {
        $restaurant = $request->user()->restaurant;
        $member = $this->find($request, $user);

        return $this->attempt(fn () => $this->team->remove($restaurant, $member, $request->user()), __('team.removed'), route('team.index'));
    }

    /** Looked up through the restaurant, so a staff id of another restaurant is a 404. */
    private function find(Request $request, int $id): User
    {
        return $request->user()->restaurant->users()->findOrFail($id);
    }

    private function attempt(callable $action, string $success, ?string $to = null): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['team' => __('team.error_'.$e->getMessage())]);
        }

        return ($to ? redirect($to) : back())->with('status', $success);
    }
}
