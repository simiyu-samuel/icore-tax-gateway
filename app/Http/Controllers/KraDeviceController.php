<?php
namespace App\Http\Controllers;

use App\Http\Requests\ActivateKraDeviceRequest;
use App\Services\KraDeviceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KraDeviceController extends Controller
{
    protected KraDeviceService $kraDeviceService;

    public function __construct(KraDeviceService $kraDeviceService)
    {
        $this->kraDeviceService = $kraDeviceService;
    }

    /**
     * Activate a KRA device (OSCU or VSCU).
     */
    public function activate(ActivateKraDeviceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $pin = $validated['pin'];
        $deviceType = $validated['device_type'];
        $data = $validated['data'] ?? [];
        $result = $this->kraDeviceService->activateDevice($pin, $deviceType, $data);
        return response()->json(['kra_response' => $result]);
    }

    /**
     * Get the status of a KRA device.
     */
    public function status(Request $request): JsonResponse
    {
        $pin = $request->query('pin');
        $deviceType = $request->query('device_type');
        $data = $request->query('data', []);
        $result = $this->kraDeviceService->getDeviceStatus($pin, $deviceType, $data);
        return response()->json(['kra_response' => $result]);
    }
} 