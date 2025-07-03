<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiClient; // Your ApiClient model
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header(config('app.icore_api_key_header', 'X-API-Key'));

        if (! $apiKey) {
            return response()->json([
                'timestamp' => now()->toISOString(),
                'status' => Response::HTTP_UNAUTHORIZED,
                'error' => 'Unauthorized',
                'message' => 'API Key missing.',
                'gatewayErrorCode' => 'ICORE_AUTH_MISSING_API_KEY',
                'traceId' => $request->attributes->get('traceId') // Assuming traceId middleware runs first
            ], Response::HTTP_UNAUTHORIZED);
        }

        $client = ApiClient::findByApiKey($apiKey); // Call your find method

        if (! $client) {
            return response()->json([
                'timestamp' => now()->toISOString(),
                'status' => Response::HTTP_UNAUTHORIZED,
                'error' => 'Unauthorized',
                'message' => 'Invalid API Key.',
                'gatewayErrorCode' => 'ICORE_AUTH_INVALID_API_KEY',
                'traceId' => $request->attributes->get('traceId')
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Store the authenticated client for later use in controllers/services
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}