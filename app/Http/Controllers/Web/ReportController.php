<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaxpayerPin;
use App\Models\KraDevice;
use App\Services\KraReportService; // Your existing report service
use App\Services\KraDeviceService; // To get device status if needed
use App\Exceptions\KraApiException; // Handle KRA errors
use Illuminate\Support\Facades\Http; // For making internal API calls
use Illuminate\Support\Facades\Auth; // For authenticated user

class ReportController extends Controller
{
    protected KraReportService $kraReportService;
    protected KraDeviceService $kraDeviceService; // Optional, if needed for UI dropdowns

    public function __construct(KraReportService $kraReportService, KraDeviceService $kraDeviceService)
    {
        $this->middleware('auth'); // Ensure user is logged in
        $this->kraReportService = $kraReportService;
        $this->kraDeviceService = $kraDeviceService;
    }

    /**
     * Show the report generation form and list available taxpayer PINs/Devices.
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $taxpayerPins = $user->taxpayerPins()->with('kraDevices')->get();

        return view('reports.index', compact('taxpayerPins'));
    }

    /**
     * Handle X Daily Report generation.
     */
    public function getXDailyReport(Request $request)
    {
        $request->validate([
            'taxpayer_pin_id' => ['required', 'exists:taxpayer_pins,id'],
            'kra_device_id' => ['required', 'exists:kra_devices,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $taxpayerPin = $user->taxpayerPins()->find($request->taxpayer_pin_id);
        $kraDevice = KraDevice::where('id', $request->kra_device_id)
                              ->where('taxpayer_pin_id', $request->taxpayer_pin_id)
                              ->first();

        if (!$taxpayerPin || !$kraDevice) {
            return back()->withErrors(['message' => 'Unauthorized or device/PIN not found.']);
        }

        try {
            // Make an INTERNAL API call to your Gateway's API endpoint
            $apiResponse = Http::withHeaders([
                'X-API-Key' => config('icore.ui_backend_api_key'), // Your UI backend's API key
            ])->get(route('api.reports.x-daily', [], false), [ // Use route helper for API route
                'gatewayDeviceId' => $kraDevice->id,
                'taxpayerPin' => $taxpayerPin->pin,
            ])->throw()->json(); // Throw exception on HTTP errors

            return view('reports.x-daily', ['report' => $apiResponse['report'], 'kraResponse' => $apiResponse['kraResponse']]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $errorMessage = $e->response ? $e->response->json('message', 'Failed to retrieve report from Gateway API.') : $e->getMessage();
            return back()->withErrors(['message' => 'Gateway API Error: ' . $errorMessage]);
        } catch (KraApiException $e) {
            return back()->withErrors(['message' => 'KRA Error: ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'An unexpected error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle Z Daily Report generation.
     */
    public function generateZDailyReport(Request $request)
    {
        $request->validate([
            'taxpayer_pin_id' => ['required', 'exists:taxpayer_pins,id'],
            'kra_device_id' => ['required', 'exists:kra_devices,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $taxpayerPin = $user->taxpayerPins()->find($request->taxpayer_pin_id);
        $kraDevice = KraDevice::where('id', $request->kra_device_id)
                              ->where('taxpayer_pin_id', $request->taxpayer_pin_id)
                              ->first();

        if (!$taxpayerPin || !$kraDevice) {
            return back()->withErrors(['message' => 'Unauthorized or device/PIN not found.']);
        }

        try {
            $apiResponse = Http::withHeaders([
                'X-API-Key' => config('icore.ui_backend_api_key'),
            ])->post(route('api.reports.z-daily', [], false), [
                'gatewayDeviceId' => $kraDevice->id,
                'taxpayerPin' => $taxpayerPin->pin,
            ])->throw()->json();

            return view('reports.z-daily', ['report' => $apiResponse['report'], 'kraResponse' => $apiResponse['kraResponse']]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            $errorMessage = $e->response ? $e->response->json('message', 'Failed to retrieve report from Gateway API.') : $e->getMessage();
            return back()->withErrors(['message' => 'Gateway API Error: ' . $errorMessage]);
        } catch (KraApiException $e) {
            return back()->withErrors(['message' => 'KRA Error: ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'An unexpected error occurred: ' . $e->getMessage()]);
        }
    }
    // ... You can add methods for PLU Report here, similar to X-Daily
}