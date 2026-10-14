<?php

namespace App\Modules\Updater\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Updater\Exceptions\UpdateException;
use App\Modules\Updater\Models\SystemUpdate;
use App\Modules\Updater\Services\UpdateApplier;
use App\Modules\Updater\Services\UpdatePackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Super admin only. Two steps: upload (validated, nothing applied) then confirm with password.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateApplier $applier) {}

    public function index(): View
    {
        return view('updater::index', [
            'current' => $this->applier->currentVersion(),
            'history' => SystemUpdate::latest()->limit(20)->get(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:'.config('updater.max_upload_kb')],
        ]);

        $dir = $this->pendingDir();
        File::ensureDirectoryExists($dir);
        $id = (string) Str::uuid();
        $path = $dir.'/'.$id.'.zip';
        $request->file('package')->move($dir, $id.'.zip');

        try {
            $package = UpdatePackage::open($path);
            $this->applier->assertApplicable($package);
        } catch (UpdateException $e) {
            File::delete($path);

            throw ValidationException::withMessages(['package' => $e->getMessage()]);
        }

        $request->session()->put('updater.pending', $id);

        return redirect()->route('admin.updates.review');
    }

    public function review(Request $request): View|RedirectResponse
    {
        $package = $this->pendingPackage($request);

        if (! $package) {
            return redirect()->route('admin.updates.index');
        }

        return view('updater::review', ['package' => $package, 'current' => $this->applier->currentVersion()]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password')->toString(), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $package = $this->pendingPackage($request);

        if (! $package) {
            return redirect()->route('admin.updates.index');
        }

        try {
            $record = $this->applier->apply($package, $request->user()->id);
        } catch (UpdateException $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        } finally {
            File::delete($this->pendingPath($request->session()->pull('updater.pending')));
        }

        return redirect()->route('admin.updates.index')->with('status', __('updater.applied', ['version' => $record->version]));
    }

    public function cancel(Request $request): RedirectResponse
    {
        File::delete($this->pendingPath($request->session()->pull('updater.pending')));

        return redirect()->route('admin.updates.index');
    }

    private function pendingPackage(Request $request): ?UpdatePackage
    {
        $path = $this->pendingPath($request->session()->get('updater.pending'));

        return is_file($path) ? UpdatePackage::open($path) : null;
    }

    private function pendingDir(): string
    {
        return storage_path('app/'.config('updater.storage').'/pending');
    }

    /** Only ever builds a path from a UUID the server itself generated. */
    private function pendingPath(?string $id): string
    {
        return $this->pendingDir().'/'.(Str::isUuid((string) $id) ? $id : 'none').'.zip';
    }
}
