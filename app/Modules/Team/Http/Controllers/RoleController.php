<?php

namespace App\Modules\Team\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\RestaurantRoleService;
use App\Modules\Auth\Support\Permissions;
use App\Modules\Team\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Custom roles: a name and a set of permissions, for staff who do not fit the built-in roles. */
class RoleController extends Controller
{
    public function __construct(private readonly TeamService $team, private readonly RestaurantRoleService $roles) {}

    public function index(Request $request): View
    {
        $restaurant = $request->user()->restaurant;
        $custom = $this->team->customRoles($restaurant)->load('permissions');
        $counts = DB::table('model_has_roles')->where('restaurant_id', $restaurant->id)->whereIn('role_id', $custom->pluck('id'))->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');

        return view('team::roles.index', ['builtIn' => TeamService::ASSIGNABLE, 'defaults' => Permissions::defaults(), 'custom' => $custom, 'counts' => $counts]);
    }

    public function create(): View
    {
        return view('team::roles.form', ['role' => null, 'groups' => $this->groups(), 'selected' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(Permissions::restaurant())]]);
        $this->roles->create($request->user()->restaurant, $data['name'], $data['permissions'] ?? []);

        return redirect()->route('team.roles.index')->with('status', __('team.role_created'));
    }

    public function edit(Request $request, int $role): View
    {
        $model = $this->find($request, $role);

        return view('team::roles.form', ['role' => $model, 'groups' => $this->groups(), 'selected' => $model->permissions->pluck('name')->all()]);
    }

    public function update(Request $request, int $role): RedirectResponse
    {
        $model = $this->find($request, $role);
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::in(Permissions::restaurant())]]);
        $model->syncPermissions(array_values(array_intersect($data['permissions'] ?? [], Permissions::restaurant())));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('team.roles.index')->with('status', __('admin.saved'));
    }

    public function destroy(Request $request, int $role): RedirectResponse
    {
        $model = $this->find($request, $role);

        if (DB::table('model_has_roles')->where('role_id', $model->id)->exists()) {
            return back()->withErrors(['role' => __('team.role_in_use')]);
        }

        $model->delete();

        return redirect()->route('team.roles.index')->with('status', __('team.role_deleted'));
    }

    /** Only this restaurant's own roles can be edited: built-in and other restaurants' roles are 404. */
    private function find(Request $request, int $id): Role
    {
        return Role::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($id);
    }

    /** @return array<string, list<string>> permissions grouped by area for the checkbox screen */
    private function groups(): array
    {
        return collect(Permissions::restaurant())->groupBy(fn ($p) => explode('.', $p)[0])->map->values()->all();
    }
}
