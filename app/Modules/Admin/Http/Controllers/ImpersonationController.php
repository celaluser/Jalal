<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * "Log in as" a restaurant owner from the super admin panel. The original admin id is kept in
 * the session; stop() is reachable by the impersonated user (it only needs that session key).
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $owner = $restaurant->owner;

        abort_if($owner === null || ! $owner->restaurant_id || $restaurant->isSuspended(), 422, __('admin.impersonate.unavailable'));
        abort_if($request->session()->has('impersonator_id'), 422, __('admin.impersonate.already'));

        $admin = $request->user();

        Log::info('Impersonation started', ['admin_id' => $admin->id, 'restaurant_id' => $restaurant->id, 'as_user_id' => $owner->id]);

        $request->session()->put('impersonator_id', $admin->id);
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');

        abort_if($adminId === null, 404);

        $admin = User::findOrFail($adminId);

        Log::info('Impersonation stopped', ['admin_id' => $admin->id, 'was_user_id' => $request->user()?->id]);

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
