<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
)

    ->withMiddleware(function (Middleware $middleware): void {
        // CORS header-k alkalmazva errorokra és visszajelzésekre?
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        // Route middleware alias-ok
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        // CORS Fallback
        $middleware->append(\App\Http\Middleware\Cors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    })->create();
