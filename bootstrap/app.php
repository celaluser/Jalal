<?php

use App\Modules\Admin\Http\Middleware\PseudoCron;
use App\Modules\Core\Http\Middleware\SecurityHeaders;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Middleware\SetTenantFromUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

// A site updated by uploading files keeps the leftovers of the older version: the package list and the compiled page templates can
// name a package this version no longer ships (a fatal "class not found" on every page). They are rebuilt automatically as soon as
// the vendor folder is newer than they are.
if (is_file($installed = __DIR__.'/../vendor/composer/installed.json')) {
    $stamp = (string) filemtime($installed);

    foreach (['packages', 'services'] as $cache) {
        $file = __DIR__.'/cache/'.$cache.'.php';

        if (is_file($file) && filemtime($file) < (int) $stamp) {
            @unlink($file);
        }
    }

    $views = __DIR__.'/../storage/framework/views';
    $marker = $views.'/.vendor-stamp';

    if (is_dir($views) && @file_get_contents($marker) !== $stamp) {
        foreach (glob($views.'/*.php') ?: [] as $compiled) {
            @unlink($compiled);
        }

        @file_put_contents($marker, $stamp);
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // iyzico posts the customer back from its hosted page; confirmation is re-checked server-side.
        $middleware->validateCsrfTokens(except: ['billing/return/*', 'auth/social/apple/callback', '*/pay/*/webhook', 'pay/*/webhook', '*/order/*/pay/return', 'order/*/pay/return', '*/reserve/*/pay/return', 'reserve/*/pay/return', 'print/*']);

        $middleware->web(append: [SecurityHeaders::class, PseudoCron::class]);

        // Route model binding must already see the tenant: tenant-scoped models fail closed (404) when
        // the context is still empty, so tenant resolution is ordered before SubstituteBindings.
        foreach ([ResolveTenant::class, SetTenantFromUser::class] as $tenantMiddleware) {
            $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: $tenantMiddleware);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // New files uploaded over an old installation, database not updated yet: a missing table or column.
        // Show a short, calm page instead of a server error (the error itself is still logged).
        $exceptions->render(function (QueryException $e, Request $request) {
            $installed = config('installer.force_installed') || is_file((string) config('installer.lock_file'));

            if (! $installed || preg_match('/no such (table|column)|Base table or view not found|Unknown column|Undefined (table|column)|doesn\'t exist|has no column/i', $e->getMessage()) !== 1) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'The site is finishing an update. Please try again in a minute.'], 503, ['Retry-After' => 60]);
            }

            try {
                return response()->view('update-pending', ['showAdminHint' => (bool) $request->user()?->isPlatformUser()], 503, ['Retry-After' => 60]);
            } catch (Throwable) {
                return response('The site is finishing an update. Please try again in a minute.', 503, ['Retry-After' => 60]);
            }
        });
    })->create();
