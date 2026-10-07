<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(DatabaseBackup $backups): View
    {
        return view('admin::system.backups', ['backups' => $backups->all()]);
    }

    public function store(DatabaseBackup $backups): RedirectResponse
    {
        try {
            $backups->create();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => __('admin.system.backup_failed')]);
        }

        return back()->with('status', __('admin.system.backup_created'));
    }

    public function download(string $name, DatabaseBackup $backups): BinaryFileResponse
    {
        return response()->download($backups->path($name) ?? abort(404));
    }

    public function destroy(string $name, DatabaseBackup $backups): RedirectResponse
    {
        abort_unless($backups->delete($name), 404);

        return back()->with('status', __('admin.cms.deleted'));
    }
}
