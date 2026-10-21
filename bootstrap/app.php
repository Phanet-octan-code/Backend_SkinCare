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
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

        // Automatically append SetLocale and EnsureAccountIsActive to web requests
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
