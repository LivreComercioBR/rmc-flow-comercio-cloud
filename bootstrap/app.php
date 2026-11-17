<?php

use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsureCompanyRole;
use App\Http\Middleware\EnsureServicesModuleEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');

    $middleware->preventRequestForgery(except: [
        'mercado-pago/webhook',
    ]);

        $middleware->alias([
            'company.context' => EnsureCompanyContext::class,
            'company.role' => EnsureCompanyRole::class,
            'services.module' => EnsureServicesModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
