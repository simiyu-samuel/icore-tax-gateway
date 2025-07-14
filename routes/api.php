<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\KraDeviceController;
use App\Http\Controllers\KraItemController;
use App\Http\Controllers\KraPurchaseController;
use App\Http\Controllers\KraInventoryController;
use App\Http\Controllers\KraSalesController;
use App\Http\Controllers\KraReportController; // Import this

// --- ICORE Tax Gateway API Test Routes (for Phase 1) ---

// Test route to verify API key authentication
// This route will hit your ApiKeyAuthenticate middleware
// If successful, you'll get a JSON response
Route::get('/test-auth', function (Request $request) {
    /** @var \App\Models\ApiClient $client */
    $client = $request->attributes->get('api_client'); // Get client from middleware
    return response()->json([
        'message' => 'Authentication successful!',
        'client_name' => $client->name,
        'client_id' => $client->id,
        'trace_id' => $request->attributes->get('traceId') // Get traceId from middleware
    ]);
});

// Test route to verify PIN authorization
Route::get('/test-auth-pin', function (Request $request) {
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
});

// This will be a placeholder for your actual API endpoint later
Route::post('/v1/invoices', function (Request $request) {
    // For now, just return a success message
    return response()->json(['message' => 'Invoice endpoint hit! This is a placeholder.', 'trace_id' => $request->attributes->get('traceId')], 200);
});

// Device activation and status endpoints
Route::post('/kra/devices/activate', [KraDeviceController::class, 'activate']);
Route::get('/kra/devices/status', [KraDeviceController::class, 'status']);

Route::prefix('v1')->group(function () {
    // Test route to verify routing
    Route::get('/test', function () {
        return response()->json(['message' => 'API routing is working!']);
    });
    
    // Debug route to test POST
    Route::post('/test-post', function () {
        return response()->json(['message' => 'POST routing is working!']);
    });
    
    // Debug route to test purchases path
    Route::get('/purchases-test', function () {
        return response()->json(['message' => 'Purchases path is working!']);
    });
    
    // KRA Device Management
    Route::post('/devices/initialize', [KraDeviceController::class, 'initialize']);
    Route::get('/devices/{gatewayDeviceId}/status', [KraDeviceController::class, 'getStatus']);
    
    // KRA Item Management
    Route::post('/items', [KraItemController::class, 'registerItem']);
    // Route::post('/items', function (Request $request) {
    //     return response()->json([
    //         'message' => 'DEBUG: POST /api/v1/items caught by temporary route!',
    //         'data_received' => $request->all(),
    //         'headers' => $request->headers->all()
    //     ]);
    // });
    Route::get('/items', [KraItemController::class, 'getItems']);
    
    // KRA Purchase Management
    Route::post('/purchases', [KraPurchaseController::class, 'sendPurchase']);
    Route::get('/purchases', [KraPurchaseController::class, 'getPurchases']);
    
    // KRA Inventory Management
    Route::post('/inventory', [KraInventoryController::class, 'sendInventoryMovement']);
    Route::get('/inventory', [KraInventoryController::class, 'getInventoryData']);
    Route::post('/inventory/movements', [KraInventoryController::class, 'sendMovement']);
    
    // KRA Sales Transaction Processing (Core)
    Route::post('/transactions', [KraSalesController::class, 'process']);
    
    // KRA Report Management
    Route::get('/reports/x-daily', [KraReportController::class, 'getXDailyReport']);
    Route::post('/reports/z-daily', [KraReportController::class, 'generateZDailyReport']); // POST as it's an action/generates
    Route::get('/reports/plu', [KraReportController::class, 'getPLUReport']);
});

// Mock Server Routes (for testing)
Route::prefix('mock')->group(function () {
    Route::post('/kra', [App\Http\Controllers\MockServerController::class, 'handleKraRequest']);
    Route::get('/health', [App\Http\Controllers\MockServerController::class, 'health']);
}); 