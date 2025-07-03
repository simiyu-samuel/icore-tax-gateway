<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

// Default Laravel 11 welcome route (keep this)
Route::get('/', function () {
    return view('welcome');
});

// --- ICORE Tax Gateway API Test Routes (for Phase 1) ---

// Test route to verify API key authentication
// This route will hit your ApiKeyAuthenticate middleware
// If successful, you'll get a JSON response
Route::get('/api/test-auth', function (Request $request) {
    /** @var \App\Models\ApiClient $client */
    $client = $request->attributes->get('api_client'); // Get client from middleware
    return response()->json([
        'message' => 'Authentication successful!',
        'client_name' => $client->name,
        'client_id' => $client->id,
        'trace_id' => $request->attributes->get('traceId') // Get traceId from middleware
    ]);
})->middleware('api.key.auth'); // Apply your custom API key authentication middleware

// Test route to verify PIN authorization
Route::get('/api/test-auth-pin', function (Request $request) {
    /** @var \App\Models\ApiClient $client */
    $client = $request->attributes->get('api_client');
    $taxpayerPin = $request->query('taxpayer_pin'); // Get PIN from query parameter

    if (!$client->isAllowedTaxpayerPin($taxpayerPin)) {
        // Return 403 Forbidden if the API key is not authorized for this specific PIN
        return response()->json([
            'timestamp' => now()->toISOString(),
            'status' => Response::HTTP_FORBIDDEN,
            'error' => 'Forbidden',
            'message' => 'API Key is not authorized for this taxpayer PIN.',
            'gatewayErrorCode' => 'ICORE_AUTH_PIN_NOT_ALLOWED',
            'traceId' => $request->attributes->get('traceId')
        ], Response::HTTP_FORBIDDEN);
    }

    return response()->json([
        'message' => "Authentication successful for PIN: {$taxpayerPin}!",
        'client_name' => $client->name,
        'client_id' => $client->id,
        'trace_id' => $request->attributes->get('traceId')
    ]);
})->middleware('api.key.auth'); // Protected by API Key auth

// This will be a placeholder for your actual API endpoint later
Route::post('/api/v1/invoices', function (Request $request) {
    // For now, just return a success message
    return response()->json(['message' => 'Invoice endpoint hit! This is a placeholder.', 'trace_id' => $request->attributes->get('traceId')], 200);
})->middleware('api.key.auth'); // Protected by API Key auth