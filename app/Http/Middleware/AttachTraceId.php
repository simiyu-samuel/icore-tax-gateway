<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str; // Make sure Str is imported

class AttachTraceId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check for an existing trace ID in the request header (e.g., from upstream services)
        // 2. If not found, generate a new UUID as the trace ID
        $traceId = $request->header('X-Trace-Id') ?? (string) Str::uuid();

        // Attach the trace ID to the request attributes for easy access in controllers, services, and exception handler
        $request->attributes->set('traceId', $traceId);

        // Add the trace ID to Laravel's logger context.
        // This means every log entry made during this request will automatically include 'trace_id'.
        logger()->withContext(['trace_id' => $traceId]);

        // Proceed with the request
        $response = $next($request);

        // Add the trace ID to the response header, so the client can use it for debugging
        if (method_exists($response, 'header')) {
            $response->header('X-Trace-Id', $traceId);
        } elseif (method_exists($response, 'withHeaders')) {
            $response = $response->withHeaders(['X-Trace-Id' => $traceId]);
        }

        return $response;
    }
}