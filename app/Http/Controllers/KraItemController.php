<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegisterKraItemRequest;
use App\Models\KraDevice;
use App\Services\KraItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;

class KraItemController extends Controller
{
    protected KraItemService $kraItemService;

    public function __construct(KraItemService $kraItemService)
    {
        $this->kraItemService = $kraItemService;
    }

    public function registerItem(RegisterKraItemRequest $request): JsonResponse
    {
        $_kraDeviceModel = $request->attributes->get('_kra_device_model');
        $itemData = $request->validated();
        try {
            $response = $this->kraItemService->registerItem($_kraDeviceModel, $itemData);
            return response()->json([
                'message' => 'Item registration process initiated.',
                'status' => $response['status'],
                'details' => $response['message'],
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA item registration: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    public function getItems(Request $request): JsonResponse
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
            $response = $this->kraItemService->getItems($kraDevice);
            return response()->json($response);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA item retrieval: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
} 