<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\SystemInfo;
use App\Modules\Admin\Services\WebCron;
use App\Modules\Core\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
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

    public function index(SystemInfo $info, WebCron $cron): View
    {
        return view('admin::system.index', [
            'cronUrl' => url('/cron/'.$cron->token()), 'cronMode' => $cron->mode(), 'tools' => array_keys(self::TOOLS) + [100 => 'scheduler', 101 => 'queue'],
            'environment' => $info->environment(),
            'limits' => $info->limits(),
            'checks' => $info->checks(),
            'workers' => $info->workers(),
            'actions' => array_keys(self::ACTIONS),
        ]);
    }

    /** One-click maintenance for hosting without a terminal. Same fixed-allowlist rule: the request picks a key, never a command. */
    private const TOOLS = [
        'migrate' => ['migrate', ['--force' => true]],
        'optimize' => ['optimize', []],
        'storage_link' => ['storage:link', []],
        'retry_failed' => ['queue:retry', ['id' => ['all']]],
        'flush_failed' => ['queue:flush', []],
        'backup' => ['system:backup', ['--keep' => 7]],
    ];

    public function tool(Request $request, WebCron $cron, string $tool): RedirectResponse
    {
        abort_unless(isset(self::TOOLS[$tool]) || in_array($tool, ['scheduler', 'queue'], true), 404);

        try {
            if (in_array($tool, ['scheduler', 'queue'], true)) {
                $result = $cron->run(20);
                $output = $result['ran'] ? ($tool === 'scheduler' ? $result['scheduler'] : $result['queue']) : __('admin.system.busy');
            } else {
                Artisan::call(...self::TOOLS[$tool]);
                $output = trim(Artisan::output());
            }
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['system' => __('admin.system.tool_failed', ['error' => Str::limit($e->getMessage(), 200)])]);
        }

        return back()->with('status', __('admin.system.tool_done'))->with('tool_output', Str::limit($output, 1500));
    }

    public function cron(Request $request, WebCron $cron): RedirectResponse
    {
        if ($request->boolean('regenerate')) {
            $cron->regenerate();
        } else {
            $mode = $request->validate(['mode' => ['required', 'in:auto,off,always']])['mode'];
            app(SettingsService::class)->set('system.web_cron', $mode);
        }

        return back()->with('status', __('admin.saved'));
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
