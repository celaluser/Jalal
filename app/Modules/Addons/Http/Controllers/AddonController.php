<?php

namespace App\Modules\Addons\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Services\AddonInstaller;
use App\Modules\Addons\Services\AddonManager;
use App\Modules\Updater\Exceptions\UpdateException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/** Super admin: install, switch on/off and remove add-ons. */
class AddonController extends Controller
{
    public function __construct(private readonly AddonManager $addons, private readonly AddonInstaller $installer) {}

    public function index(): View
    {
        return view('addons-admin::index', ['addons' => $this->addons->all(), 'manager' => $this->addons, 'uploads' => config('addons.upload_enabled') && ! config('demo.enabled')]);
    }

    public function upload(Request $request): RedirectResponse
    {
        abort_unless(config('addons.upload_enabled') && ! config('demo.enabled'), 403);
        $request->validate(['package' => ['required', 'file', 'mimes:zip', 'max:'.config('updater.max_upload_kb')]]);

        $dir = storage_path('app/addon-uploads');
        File::ensureDirectoryExists($dir);
        $name = Str::uuid().'.zip';
        $request->file('package')->move($dir, $name);

        try {
            $manifest = $this->installer->install($dir.'/'.$name);
        } catch (UpdateException|RuntimeException $e) {
            throw ValidationException::withMessages(['package' => $e->getMessage()]);
        } finally {
            File::delete($dir.'/'.$name);
        }

        return back()->with('status', __('addons.installed', ['name' => $manifest['name']]));
    }

    public function toggle(string $slug): RedirectResponse
    {
        abort_unless(AddonManager::validSlug($slug) && $this->addons->manifest($slug), 404);

        try {
            $this->addons->enabled($slug) ? $this->installer->disable($slug) : $this->installer->enable($slug);
        } catch (RuntimeException $e) {
            return back()->withErrors(['addon' => $e->getMessage()]);
        }

        return back()->with('status', __('addons.updated'));
    }

    public function destroy(string $slug): RedirectResponse
    {
        abort_unless(config('addons.upload_enabled') && ! config('demo.enabled'), 403);
        abort_unless(AddonManager::validSlug($slug) && $this->addons->manifest($slug), 404);
        $this->installer->uninstall($slug);

        return back()->with('status', __('addons.removed'));
    }
}
