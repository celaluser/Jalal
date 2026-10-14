<?php

namespace App\Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The branch dropdown in the top bar: pick a branch, or "all branches". */
class BranchSwitchController extends Controller
{
    public function __invoke(Request $request, BranchContext $context): RedirectResponse
    {
        $id = $request->input('branch') === 'all' ? null : (int) $request->input('branch');

        abort_unless($context->switchTo($id, $request->user()), 403);

        return back();
    }
}
