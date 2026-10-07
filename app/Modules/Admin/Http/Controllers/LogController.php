<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\LogReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request, LogReader $logs): View
    {
        $files = $logs->files();
        $file = (string) $request->query('file', $files[0]['name'] ?? '');
        $level = (string) $request->query('level', '');
        $search = trim((string) $request->query('q', ''));

        return view('admin::system.logs', [
            'files' => $files,
            'file' => $file,
            'level' => $level,
            'search' => $search,
            'levels' => LogReader::LEVELS,
            'entries' => $logs->entries($file, $level ?: null, $search ?: null),
        ]);
    }

    public function clear(Request $request, LogReader $logs): RedirectResponse
    {
        abort_unless($logs->clear((string) $request->input('file')), 404);

        return back()->with('status', __('admin.system.log_cleared'));
    }
}
