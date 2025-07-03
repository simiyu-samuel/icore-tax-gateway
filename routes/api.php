<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test-auth', function (Request $request) {
    // This route will hit your ApiKeyAuthenticate middleware
    // If successful, you'll get this JSON response
    /** @var \App\Models\ApiClient $client */
    $client = $request->attributes->get('api_client');
    return response()->json([
        'message' => 'Authentication successful!',
        'client_name' => $client->name,
        'client_id' => $client->id,
        'trace_id' => $request->attributes->get('traceId')
    ]);
})->middleware('api.key.auth'); // Apply your custom API key authentication middleware

Route::get('/test-auth-pin', function (Request $request) {
    /** @var \App\Models\ApiClient $client */
    $client = $request->attributes->get('api_client');
    $taxpayerPin = $request->query('taxpayer_pin'); // Get PIN from query parameter

    if (!$client->isAllowedTaxpayerPin($taxpayerPin)) {
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
})->middleware('api.key.auth');

// This will be your real API endpoint later
Route::post('/v1/invoices', function (Request $request) {
    return response()->json(['message' => 'Invoice endpoint hit!'], 200);
})->middleware('api.key.auth'); // Protected by API Key auth