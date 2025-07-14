<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class DebugApiItemsRoute
{
    public function handle(Request $request, Closure $next)
    {
        $traceId = $request->attributes->get('traceId');
        Log::debug("API Middleware (After Auth): Attempting to match route for {$request->method()} {$request->path()} (Trace ID: {$traceId})");

        if ($request->method() === 'POST' && str_contains($request->path(), 'api/v1/items')) {
            Log::debug("!!! Specific DEBUG DUMP triggered for POST api/v1/items at route matching phase (Trace ID: {$traceId}) !!!");
            try {
                $matchedRoute = Route::getRoutes()->match($request);
                // Uncomment the next line to dump the matched route (for debugging)
                // dd($matchedRoute);
            } catch (\Throwable $e) {
                // Uncomment the next line to dump the error (for debugging)
                // dd('Error matching route in debug middleware: ' . $e->getMessage() . ' Path: ' . $request->path());
            }
        }
        return $next($request);
    }
} 