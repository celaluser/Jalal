<?php

use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Http\Middleware\SetTenantFromUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // iyzico posts the customer back from its hosted page; confirmation is re-checked server-side.
        $middleware->validateCsrfTokens(except: ['billing/return/*', 'auth/social/apple/callback', '*/pay/*/webhook', 'pay/*/webhook', '*/order/*/pay/return', 'order/*/pay/return', 'print/*']);

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
    })->create();
