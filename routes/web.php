<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\ReportController; // Import your Web Report Controller

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // ICORE Tax Gateway UI Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/x-daily', [ReportController::class, 'getXDailyReport'])->name('reports.x-daily');
    Route::post('/reports/z-daily', [ReportController::class, 'generateZDailyReport'])->name('reports.z-daily');
    // Add PLU reports here later
});

require __DIR__.'/auth.php'; // Breeze authentication routes

// ... all your existing API routes ...
Route::middleware('api')->group(function () {
    // ... other API routes ...

    // KRA Report Management - Add route names for easy reference from UI backend
    Route::get('/reports/x-daily', [KraReportController::class, 'getXDailyReport'])->name('api.reports.x-daily');
    Route::post('/reports/z-daily', [KraReportController::class, 'generateZDailyReport'])->name('api.reports.z-daily');
    Route::get('/reports/plu', [KraReportController::class, 'getPLUReport'])->name('api.reports.plu');
});