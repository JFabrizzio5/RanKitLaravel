<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\DespertarAvisosVencimiento::class,
        ]);

        $middleware->alias([
            'superadmin' => \App\Http\Middleware\SuperAdmin::class,
            'plan.organizador' => \App\Http\Middleware\RequierePlanOrganizador::class,
        ]);

        // Las pasarelas no mandan token CSRF (se validan por firma).
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'mercadopago/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
