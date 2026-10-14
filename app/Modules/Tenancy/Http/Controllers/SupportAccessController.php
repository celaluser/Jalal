<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Services\SupportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The owner lets the platform team sign in for 24 hours, or takes that back. */
class SupportAccessController extends Controller
{
    public function __construct(private readonly SupportAccess $access) {}

    public function grant(Request $request): RedirectResponse
    {
        $this->access->grant($request->user()->restaurant);

        return back()->with('status', __('support_access.granted'));
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $this->access->withdraw($request->user()->restaurant);

        return back()->with('status', __('support_access.withdrawn'));
    }
}
