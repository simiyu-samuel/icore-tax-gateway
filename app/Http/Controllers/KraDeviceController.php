<?php
namespace App\Http\Controllers;

use App\Http\Requests\ActivateKraDeviceRequest;
use App\Services\KraDeviceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\InitializeKraDeviceRequest;
use App\Models\KraDevice;
use Illuminate\Validation\ValidationException;

class KraDeviceController extends Controller
{
    protected KraDeviceService $kraDeviceService;

    public function __construct(KraDeviceService $kraDeviceService)
    {
        $this->kraDeviceService = $kraDeviceService;
    }

    /**
     * Initialize/activate a KRA device (OSCU or VSCU).
     * POST /api/v1/devices/initialize
     * @param InitializeKraDeviceRequest $request
     * @return JsonResponse
     */
    public function initialize(InitializeKraDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Get the taxpayer PIN directly from the validated request's merge property or the authorized client
        $taxpayerPin = $request->input('taxpayerPin'); // This comes from validated() data

        // Make sure the KraDeviceService uses this taxpayerPin
        $data['taxpayerPin'] = $taxpayerPin;

        try {
            $kraDevice = $this->kraDeviceService->initializeDevice($data);

            return response()->json([
                'message' => 'KRA device initialized successfully.',
                'gatewayDeviceId' => $kraDevice->id,
                'kraScuId' => $kraDevice->kra_scu_id,
                'deviceType' => $kraDevice->device_type,
                'status' => $kraDevice->status,
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            // KraApiException handles rendering itself, so just re-throw
            throw $e;
        } catch (\Throwable $e) {
            // Catch any other unexpected errors and re-throw for global handler
            logger()->error("Unexpected error during KRA device initialization: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    /**
     * Get the status of a specific KRA device.
     * GET /api/v1/devices/{gatewayDeviceId}/status
     * @param Request $request
     * @param string $gatewayDeviceId The UUID of the KRA device in our system.
     * @return JsonResponse
     */
    public function getStatus(Request $request, string $gatewayDeviceId): JsonResponse
    {
        // Authorization check: Ensure this device belongs to the authenticated client's allowed taxpayer PINs.
        // This is crucial for multi-tenancy.
        /** @var ApiClient $apiClient */
        $apiClient = $request->attributes->get('api_client');
        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);

        $kraDevice = KraDevice::where('id', $gatewayDeviceId)
                              ->whereHas('taxpayerPin', function($query) use ($allowedPins) {
                                  $query->whereIn('pin', $allowedPins);
                              })
                              ->firstOrFail(); // Will throw ModelNotFoundException (404) if not found or not authorized

        try {
            $statusData = $this->kraDeviceService->getDeviceStatus($kraDevice);

            return response()->json([
                'message' => 'KRA device status retrieved successfully.',
                'gatewayDeviceId' => $kraDevice->id,
                'kraScuId' => $statusData['kraScuId'],
                'firmwareVersion' => $statusData['firmwareVersion'],
                'hardwareRevision' => $statusData['hardwareRevision'],
                'currentZReportCount' => $statusData['currentZReportCount'],
                'lastRemoteAuditDate' => $statusData['lastRemoteAuditDate'],
                'lastLocalAuditDate' => $statusData['lastLocalAuditDate'],
                'operationalStatus' => $statusData['operationalStatus'],
                'errorMessage' => $statusData['errorMessage'],
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA device status retrieval: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
} 