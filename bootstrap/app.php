<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply ApiKeyAuthenticate and AttachTraceId to all API routes
        $middleware->api(prepend: [
            \App\Http\Middleware\ApiKeyAuthenticate::class,
            \App\Http\Middleware\AttachTraceId::class,
        ]);

        // Add debugging middleware for API requests
        $middleware->api(append: [
            \App\Http\Middleware\DebugApiItemsRoute::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();