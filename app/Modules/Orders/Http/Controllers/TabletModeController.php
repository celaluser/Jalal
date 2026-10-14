<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tablet mode for kitchen screens and counters: the sidebar folds away, controls get larger and the screen stays awake.
 * It is a per-browser-session choice.
 */
class TabletModeController extends Controller
{
    public const KEY = 'tablet_mode';

    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->put(self::KEY, ! $request->session()->get(self::KEY, false));

        return back();
    }
}
