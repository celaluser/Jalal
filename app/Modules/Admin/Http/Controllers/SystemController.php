<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\SystemInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/** System overview: environment, health of scheduler/queue, and the maintenance "clear cache" actions. */
class SystemController extends Controller
{
    /** Fixed allowlist: the request only picks a key, never a command name. */
    private const ACTIONS = [
        'cache' => 'cache:clear',
        'views' => 'view:clear',
        'config' => 'config:clear',
        'routes' => 'route:clear',
        'all' => 'optimize:clear',
    ];

    public function index(SystemInfo $info): View
    {
        return view('admin::system.index', [
            'environment' => $info->environment(),
            'limits' => $info->limits(),
            'checks' => $info->checks(),
            'workers' => $info->workers(),
            'actions' => array_keys(self::ACTIONS),
        ]);
    }

    public function clear(string $action): RedirectResponse
    {
        abort_unless(isset(self::ACTIONS[$action]), 404);

        try {
            Artisan::call(self::ACTIONS[$action]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['system' => __('admin.system.clear_failed')]);
        }

        return back()->with('status', __('admin.system.cleared'));
    }
}
