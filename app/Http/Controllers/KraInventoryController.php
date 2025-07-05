<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendKraInventoryRequest;
use App\Http\Requests\SendKraInventoryMovementRequest;
use App\Models\KraDevice;
use App\Services\KraInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;

class KraInventoryController extends Controller
{
    protected KraInventoryService $kraInventoryService;

    public function __construct(KraInventoryService $kraInventoryService)
    {
        $this->kraInventoryService = $kraInventoryService;
    }

    /**
     * Sends inventory movement data to KRA.
     * POST /api/v1/inventory
     * @param SendKraInventoryRequest $request
     * @return JsonResponse
     */
    public function sendInventoryMovement(SendKraInventoryRequest $request): JsonResponse
    {
        $_kraDeviceModel = $request->attributes->get('_kra_device_model');
        $inventoryData = $request->validated();

        try {
            $response = $this->kraInventoryService->sendInventoryMovement($_kraDeviceModel, $inventoryData);
            return response()->json([
                'message' => 'Inventory movement data sent to KRA successfully.',
                'details' => $response['message'] ?? 'Operation completed.',
                'status' => $response['status'] ?? 'UNKNOWN',
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA inventory movement submission: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    /**
     * Sends inventory movement data (stock in/out) to KRA.
     * POST /api/v1/inventory/movements
     * @param SendKraInventoryMovementRequest $request
     * @return JsonResponse
     */
    public function sendMovement(SendKraInventoryMovementRequest $request): JsonResponse
    {
        /** @var KraDevice $_kraDeviceModel */
        $_kraDeviceModel = $request->attributes->get('_kra_device_model'); // Access merged model

        $inventoryData = $request->validated();

        try {
            $response = $this->kraInventoryService->sendInventoryMovement($_kraDeviceModel, $inventoryData);
            return response()->json([
                'message' => 'Inventory movement data sent to KRA successfully.',
                'status' => $response['status'],
                'details' => $response['message'] ?? 'Operation completed.',
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA inventory movement submission: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    /**
     * Retrieves inventory data from KRA.
     * GET /api/v1/inventory
     * @param Request $request
     * @return JsonResponse
     */
    public function getInventoryData(Request $request): JsonResponse
    {
        $request->validate([
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
        ]);

        $apiClient = $request->attributes->get('api_client');
        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);

        $kraDevice = KraDevice::where('id', $request->input('gatewayDeviceId'))
                              ->whereHas('taxpayerPin', function($query) use ($allowedPins) {
                                  $query->whereIn('pin', $allowedPins);
                              })
                              ->firstOrFail();

        try {
            $filterData = $request->only(['branchId', 'itemCode', 'startDate', 'endDate']);
            $response = $this->kraInventoryService->getInventoryData($kraDevice, $filterData);
            return response()->json($response);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA inventory data retrieval: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
}
