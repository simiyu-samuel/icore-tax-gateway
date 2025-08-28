<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\KraDevice;
use Illuminate\Support\Facades\Log; // <-- Add this import

class RegisterKraItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ApiClient $apiClient */
        $apiClient = $this->attributes->get('api_client');
        $traceId = $this->attributes->get('traceId'); // Get traceId

        Log::info("Authorize check for RegisterKraItemRequest (Trace ID: {$traceId})", [
            'api_client_exists' => (bool)$apiClient,
            'api_client_active' => $apiClient ? $apiClient->is_active : null,
            'input_gatewayDeviceId' => $this->input('gatewayDeviceId'),
            'input_taxpayerPin' => $this->input('taxpayerPin'),
        ]);

        // Basic checks for API client
        if (!$apiClient || !$apiClient->is_active) {
            Log::warning("Authorize check failed: API Client not found or inactive (Trace ID: {$traceId})");
            return false;
        }

        // Authorization: Ensure the gatewayDeviceId belongs to the authenticated API client's taxpayer
        $gatewayDeviceId = $this->input('gatewayDeviceId');
        $taxpayerPin = $this->input('taxpayerPin');

        // Find the KraDevice and ensure it's linked to the provided taxpayerPin
        $kraDevice = KraDevice::where('id', $gatewayDeviceId)
                              ->whereHas('taxpayerPin', function($query) use ($apiClient, $taxpayerPin) {
                                  $query->where('pin', $taxpayerPin)
                                  ->whereIn('taxpayer_pin_id', $apiClient->taxpayerPins()->pluck('id'));
                              })
                              ->first();

        Log::info("Authorize check: KraDevice lookup result (Trace ID: {$traceId})", [
            'kra_device_found' => (bool)$kraDevice,
            'kra_device_id' => $kraDevice ? $kraDevice->id : null,
            'kra_device_taxpayer_id' => $kraDevice ? $kraDevice->taxpayer_pin_id : null,
            'target_taxpayer_pin' => $taxpayerPin,
        ]);

        // Check if the API client is allowed to operate on this specific taxpayer PIN
        $isPinAllowedForClient = $apiClient->taxpayerPins()->where('pin', $taxpayerPin)->exists();

        Log::info("Authorize check: API Client PIN permission (Trace ID: {$traceId})", [
            'is_pin_allowed_for_client' => $isPinAllowedForClient,
        ]);

        if (!$kraDevice || !$isPinAllowedForClient) { // This is the crucial line for failure
            Log::warning("Authorize check failed: KRA Device not found/linked OR Taxpayer PIN not allowed for client (Trace ID: {$traceId})", [
                'kra_device_valid' => (bool)$kraDevice,
                'is_pin_allowed' => $isPinAllowedForClient
            ]);
            return false;
        }

        // Store the KraDevice model for easy access in the controller/service
        $this->attributes->set('_kra_device_model', $kraDevice);

        Log::info("Authorize check PASSED for RegisterKraItemRequest (Trace ID: {$traceId})");
        return true;
    }

    public function rules(): array
    {
        // Keep your rules as they were. We'll address validation errors if authorize passes.
        return [
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
            'itemCode' => ['required', 'string', 'max:50'],
            'itemClassificationCode' => ['required', 'string', 'max:50'],
            'itemName' => ['required', 'string', 'max:255'],
            'itemTypeCode' => ['nullable', 'string', 'max:10'],
            'itemStandard' => ['nullable', 'string', 'max:255'],
            'originCountryCode' => ['nullable', 'string', 'max:5'],
            'packagingUnitCode' => ['required', 'string', 'max:10'],
            'quantityUnitCode' => ['required', 'string', 'max:10'],
            'additionalInfo' => ['nullable', 'string', 'max:255'],
            'initialWholesaleUnitPrice' => ['required', 'numeric', 'min:0'],
            'initialQuantity' => ['required', 'numeric', 'min:0'],
            'averageWholesaleUnitPrice' => ['nullable', 'numeric', 'min:0'],
            'defaultSellingUnitPrice' => ['required', 'numeric', 'min:0'],
            'taxType' => ['required', 'string', 'max:5'],
            'remark' => ['nullable', 'string', 'max:255'],
            'inUse' => ['required', 'boolean'],
            'registerUserId' => ['nullable', 'string', 'max:50'],
            'registerDate' => ['nullable', 'date_format:YmdHis'],
            'updateUserId' => ['nullable', 'string', 'max:50'],
            'updateDate' => ['nullable', 'date_format:YmdHis'],
            'safetyQuantity' => ['nullable', 'numeric', 'min:0'],
            'useBarcode' => ['nullable', 'boolean'],
            'changeAllowed' => ['nullable', 'boolean'],
            'useAdditionalInfoAllowed' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'gatewayDeviceId.exists' => 'The provided Gateway Device ID does not exist or is not linked to the given Taxpayer PIN.',
            'taxpayerPin.exists' => 'The provided Taxpayer PIN does not exist in our system or is inactive.',
        ];
    }
}
