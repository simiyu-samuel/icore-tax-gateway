<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response; // Required if you use Response::HTTP_ constants directly

// Import all your API Controllers
use App\Http\Controllers\KraDeviceController;
use App\Http\Controllers\KraItemController;
use App\Http\Controllers\KraPurchaseController;
use App\Http\Controllers\KraInventoryController;
use App\Http\Controllers\KraSalesController;
use App\Http\Controllers\KraReportController;
use App\Http\Controllers\MockServerController; // Assuming this exists for your mock routes

// In Laravel 11, if bootstrap/app.php's withRouting defines
// api: __DIR__.'/../routes/api.php',
// then all routes in THIS file automatically get the `/api` prefix
// and the middleware defined in `$middleware->api()` in bootstrap/app.php.

// --- API Version 1 Routes ---
// ALL routes inside this group will be prefixed with `/api/v1/`
Route::prefix('v1')->group(function () {

    // Temporary ping route for debugging
    Route::get('/ping', function () {
        return response()->json(['message' => 'pong']);
    });

    // --- Core Test Routes (move from top-level to be under /api/v1/) ---
    // Test route to verify API key authentication
    Route::get('/test-auth', function (Request $request) {
        /** @var \App\Models\ApiClient $client */
        $client = $request->attributes->get('api_client');
        return response()->json([
            'message' => 'Authentication successful!',
            'client_name' => $client->name,
            'client_id' => $client->id,
            'trace_id' => $request->attributes->get('traceId')
        ]);
    });

    // Test route to verify PIN authorization
    // NOTE: isAllowedTaxpayerPin method likely belongs on ApiClient model, not a direct call
    // Ensure this method exists on your ApiClient model or simplify this check
    Route::get('/test-auth-pin', function (Request $request) {
        /** @var \App\Models\ApiClient $client */
        $client = $request->attributes->get('api_client');
        $taxpayerPin = $request->query('taxpayer_pin');

        // This method might not exist directly as client->isAllowedTaxpayerPin()
        // It should be something like: $client->taxpayerPins()->where('pin', $taxpayerPin)->exists();
        // If you are using `allowed_taxpayer_pins` as a comma-separated string, use:
        $isAllowed = in_array($taxpayerPin, explode(',', $client->allowed_taxpayer_pins ?? ''));

        if (!$isAllowed) {
            return response()->json([
                'timestamp' => now()->toISOString(),
                'status' => Response::HTTP_FORBIDDEN,
                'error' => 'Forbidden',
                'message' => 'API Key is not authorized for this taxpayer PIN.',
                'gatewayErrorCode' => 'ICORE_AUTH_PIN_NOT_ALLOWED',
                'trace_id' => $request->attributes->get('traceId')
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => "Authentication successful for PIN: {$taxpayerPin}!",
            'client_name' => $client->name,
            'client_id' => $client->id,
            'trace_id' => $request->attributes->get('traceId')
        ]);
    });

    // --- Placeholder/Debug Routes (inside v1 prefix) ---
    Route::post('/invoices', function (Request $request) {
        // This is a placeholder. Your actual transactions route is /transactions
        return response()->json(['message' => 'Invoice endpoint hit! This is a placeholder under /api/v1/.', 'trace_id' => $request->attributes->get('traceId')], 200);
    })->name('api.invoices.placeholder'); // Give it a name to distinguish

    // Debug route to verify routing
    Route::get('/test', function () {
        return response()->json(['message' => 'API routing is working! (GET /api/v1/test)']);
    });

    // Debug route to test POST
    Route::post('/test-post', function () {
        return response()->json(['message' => 'POST routing is working! (POST /api/v1/test-post)']);
    });

    // Debug route to test purchases path
    Route::get('/purchases-test', function () {
        return response()->json(['message' => 'Purchases path is working!']);
    });
    // --- End Placeholder/Debug Routes ---


    // KRA Device Management (consolidated under /api/v1/devices)
    // Removed old /kra/devices/activate and /kra/devices/status as they are duplicates/inconsistent
    Route::post('/devices/initialize', [KraDeviceController::class, 'initialize'])->name('api.devices.initialize');
    Route::get('/devices/{gatewayDeviceId}/status', [KraDeviceController::class, 'getStatus'])->name('api.devices.status');

    // KRA Item Management
    Route::post('/items', [KraItemController::class, 'registerItem'])->name('api.items.register');
    Route::get('/items', [KraItemController::class, 'getItems'])->name('api.items.get');

    // KRA Purchase Management
    Route::post('/purchases', [KraPurchaseController::class, 'sendPurchase'])->name('api.purchases.send');
    Route::get('/purchases', [KraPurchaseController::class, 'getPurchases'])->name('api.purchases.get');

    // KRA Inventory Management
    // Removed old /inventory and /inventory/movements if they were separate.
    // The main one is /inventory/movements
    Route::post('/inventory/movements', [KraInventoryController::class, 'sendMovement'])->name('api.inventory.send');
    // If you have a getInventoryData method, ensure it's defined in KraInventoryController
    // Route::get('/inventory', [KraInventoryController::class, 'getInventoryData'])->name('api.inventory.get');

    // KRA Sales Transaction Processing (Core)
    // This is the correct route for /api/v1/transactions
    Route::post('/transactions', [KraSalesController::class, 'process'])->name('api.transactions.process');

    // KRA Report Management
    Route::get('/reports/x-daily', [KraReportController::class, 'getXDailyReport'])->name('api.reports.x-daily');
    Route::post('/reports/z-daily', [KraReportController::class, 'generateZDailyReport'])->name('api.reports.z-daily');
    Route::get('/reports/plu', [KraReportController::class, 'getPLUReport'])->name('api.reports.plu');

}); // End of Route::prefix('v1')->group()

// --- Mock Server Routes (These are outside /api/v1/ and will be hit at /api/mock/...) ---
// These are fine to be outside the /v1 prefix if they are truly separate.
Route::prefix('mock')->group(function () {
    Route::post('/kra', [MockServerController::class, 'handleKraRequest'])->name('api.mock.kra');
    Route::get('/health', [MockServerController::class, 'health'])->name('api.mock.health');
});
