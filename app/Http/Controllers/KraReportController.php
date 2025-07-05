<?php

namespace App\Http\Controllers;

use App\Models\KraDevice;
use App\Services\KraReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KraReportController extends Controller
{
    protected KraReportService $kraReportService;

    public function __construct(KraReportService $kraReportService)
    {
        $this->kraReportService = $kraReportService;
    }

    /**
     * Get an X Daily Report from KRA.
     * GET /api/v1/reports/x-daily
     * @param Request $request
     * @return JsonResponse
     */
    public function getXDailyReport(Request $request): JsonResponse
    {
        $request->validate([
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
        ]);

        /** @var ApiClient $apiClient */
        $apiClient = $request->attributes->get('api_client');
        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);

        $kraDevice = KraDevice::where('id', $request->input('gatewayDeviceId'))
                              ->whereHas('taxpayerPin', function($query) use ($allowedPins) {
                                  $query->whereIn('pin', $allowedPins);
                              })
                              ->firstOrFail();

        try {
            $response = $this->kraReportService->getXDailyReport($kraDevice);
            return response()->json($response);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA X Daily Report retrieval: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    /**
     * Generate and Get a Z Daily Report from KRA.
     * POST /api/v1/reports/z-daily
     * @param Request $request
     * @return JsonResponse
     */
    public function generateZDailyReport(Request $request): JsonResponse
    {
        $request->validate([
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
        ]);

        /** @var ApiClient $apiClient */
        $apiClient = $request->attributes->get('api_client');
        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);

        $kraDevice = KraDevice::where('id', $request->input('gatewayDeviceId'))
                              ->whereHas('taxpayerPin', function($query) use ($allowedPins) {
                                  $query->whereIn('pin', $allowedPins);
                              })
                              ->firstOrFail();

        try {
            $response = $this->kraReportService->generateZDailyReport($kraDevice);
            return response()->json($response);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA Z Daily Report generation: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }

    /**
     * Get a PLU Report from KRA.
     * GET /api/v1/reports/plu
     * @param Request $request
     * @return JsonResponse
     */
    public function getPLUReport(Request $request): JsonResponse
    {
        $request->validate([
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);

        /** @var ApiClient $apiClient */
        $apiClient = $request->attributes->get('api_client');
        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);

        $kraDevice = KraDevice::where('id', $request->input('gatewayDeviceId'))
                              ->whereHas('taxpayerPin', function($query) use ($allowedPins) {
                                  $query->whereIn('pin', $allowedPins);
                              })
                              ->firstOrFail();

        try {
            $startDate = $request->query('startDate');
            $endDate = $request->query('endDate');
            $response = $this->kraReportService->getPLUReport($kraDevice, $startDate, $endDate);
            return response()->json($response);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA PLU Report retrieval: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
}
