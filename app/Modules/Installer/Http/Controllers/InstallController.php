<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Installer\Services\DatabaseConnector;
use App\Modules\Installer\Services\InstallerService;
use App\Modules\Installer\Services\RequirementsChecker;
use App\Modules\Licensing\Services\LicenseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Five step wizard: requirements, purchase code, database, admin account, install.
 * Progress is kept in the (file based) session until the final step.
 */
class InstallController extends Controller
{
    public function __construct(
        private readonly RequirementsChecker $requirements,
        private readonly LicenseManager $licenses,
        private readonly DatabaseConnector $database,
        private readonly InstallerService $installer,
    ) {}

    public function welcome(): View
    {
        return view('installer::requirements', [
            'checks' => $this->requirements->checks(),
            'passes' => $this->requirements->passes(),
            'step' => 1,
        ]);
    }

    public function license(Request $request): View|RedirectResponse
    {
        if (! $this->requirements->passes()) {
            return redirect()->route('install.welcome');
        }

        return view('installer::license', ['step' => 2, 'driver' => config('licensing.driver')]);
    }

    public function storeLicense(Request $request): RedirectResponse
    {
        $data = $request->validate(['purchase_code' => ['required', 'string', 'max:100']]);

        $result = $this->licenses->verify($data['purchase_code']);

        // Rejected codes block; an unreachable licence server only marks the licence unverified.
        if (! $result->valid && $result->reachable) {
            throw ValidationException::withMessages(['purchase_code' => $result->message ?: __('installer.license_rejected')]);
        }

        $request->session()->put('installer.license', ['code' => trim($data['purchase_code']), 'valid' => $result->valid]);

        return redirect()->route('install.database')->with('status', $result->valid ? null : __('installer.license_unverified'));
    }

    public function database(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('installer.license')) {
            return redirect()->route('install.license');
        }

        return view('installer::database', ['step' => 3]);
    }

    public function storeDatabase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', 'in:mysql,sqlite'],
            'host' => ['required_if:driver,mysql', 'nullable', 'string', 'max:190'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:190'],
            'username' => ['required_if:driver,mysql', 'nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
        ]);

        if ($data['driver'] === 'sqlite') {
            $data['database'] = $this->sqlitePath($data['database']);
        }

        if ($error = $this->database->test($data)) {
            throw ValidationException::withMessages(['database' => __('installer.database_failed', ['error' => $error])]);
        }

        $request->session()->put('installer.database', $data);

        return redirect()->route('install.admin');
    }

    public function admin(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('installer.database')) {
            return redirect()->route('install.database');
        }

        return view('installer::admin', ['step' => 4, 'url' => rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/')]);
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_url' => ['required', 'url', 'max:190'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $session = $request->session();

        if (! $session->has('installer.license') || ! $session->has('installer.database')) {
            return redirect()->route('install.welcome');
        }

        try {
            $this->installer->install([
                'license' => $session->get('installer.license'),
                'database' => $session->get('installer.database'),
                'site' => ['name' => $data['site_name'], 'url' => rtrim($data['site_url'], '/')],
                'admin' => ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']],
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['email' => __('installer.install_failed', ['error' => $e->getMessage()])]);
        }

        $session->flush();

        return redirect()->route('install.finished');
    }

    public function finished(): View
    {
        return view('installer::finished', ['step' => 5, 'cronUrl' => url('/cron/'.app(\App\Modules\Admin\Services\WebCron::class)->token())]);
    }

    /** SQLite files must live inside the project's database folder. */
    private function sqlitePath(string $name): string
    {
        $file = basename($name) ?: 'database.sqlite';

        return database_path($file);
    }
}
