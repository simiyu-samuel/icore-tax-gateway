<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendKraPurchaseRequest;
use App\Models\KraDevice;
use App\Services\KraPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;

class KraPurchaseController extends Controller
{
    protected KraPurchaseService $kraPurchaseService;

    public function __construct(KraPurchaseService $kraPurchaseService)
    {
        $this->kraPurchaseService = $kraPurchaseService;
    }

    /**
     * Sends purchase data to KRA.
     * POST /api/v1/purchases
     * @param SendKraPurchaseRequest $request
     * @return JsonResponse
     */
    public function sendPurchase(SendKraPurchaseRequest $request): JsonResponse
    {
        $_kraDeviceModel = $request->attributes->get('_kra_device_model');
        $purchaseData = $request->validated();

        try {
            $response = $this->kraPurchaseService->sendPurchase($_kraDeviceModel, $purchaseData);
            return response()->json([
                'message' => 'Purchase data sent to KRA successfully.',
                'details' => $response['message'] ?? 'Operation completed.',
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA purchase data submission: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    /**
     * Retrieves purchase data from KRA.
     * GET /api/v1/purchases
     * @param Request $request
     * @return JsonResponse
     */
    public function getPurchases(Request $request): JsonResponse
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
            $filterData = $request->only(['invoiceId', 'startDate', 'endDate']);
            $response = $this->kraPurchaseService->getPurchases($kraDevice, $filterData);
            return response()->json($response);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA purchase data retrieval: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
}
