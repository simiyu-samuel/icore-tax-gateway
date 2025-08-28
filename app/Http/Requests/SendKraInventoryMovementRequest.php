<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\KraDevice;

class SendKraInventoryMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var ApiClient $apiClient */
        $apiClient = $this->attributes->get('api_client');

        if (!$apiClient || !$apiClient->is_active) {
            logger()->error("API client is null or inactive", [
                'api_client' => $apiClient ? $apiClient->id : null,
                'trace_id' => $this->attributes->get('traceId')
            ]);
            return false;
        }

        $gatewayDeviceId = $this->input('gatewayDeviceId');
        $taxpayerPin = $this->input('taxpayerPin');

        $kraDevice = KraDevice::where('id', $gatewayDeviceId)
                              ->whereHas('taxpayerPin', function($query) use ($apiClient, $taxpayerPin) {
                                  $query->where('pin', $taxpayerPin)
                                  ->whereIn('taxpayer_pin_id', $apiClient->taxpayerPins()->pluck('id'));
                              })
                              ->first();

        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);
        if (!in_array($taxpayerPin, $allowedPins)) {
            logger()->error("Taxpayer PIN not allowed for this API client", [
                'taxpayerPin' => $taxpayerPin,
                'allowedPins' => $allowedPins,
                'trace_id' => $this->attributes->get('traceId')
            ]);
            return false;
        }

        if (!$kraDevice) {
            logger()->error("KraDevice not found", [
                'gatewayDeviceId' => $gatewayDeviceId,
                'taxpayerPin' => $taxpayerPin,
                'trace_id' => $this->attributes->get('traceId')
            ]);
            return false;
        }

        $this->attributes->set('_kra_device_model', $kraDevice);
        logger()->info("KraDevice model attached successfully", [
            'deviceId' => $kraDevice->id,
            'trace_id' => $this->attributes->get('traceId')
        ]);
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Rules based on KRA documentation (Doc 1, Section 24.8.7.1, Page 24)
        return [
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
            'branchId' => ['required', 'string', 'max:10'], // bhfId
            'itemClassificationCode' => ['required', 'string', 'max:50'], // itemClsCd
            'itemCode' => ['required', 'string', 'max:50'], // itemCd
            'quantity' => ['required', 'numeric'], // qty (can be negative for stock-out, positive for stock-in)
            'updateDate' => ['required', 'date_format:YmdHis'], // updDt - YYYYMMDDHHMMSS
        ];
    }

    public function messages(): array
    {
        return [
            'updateDate.date_format' => 'The update date must be in YYYYMMDDHHMMSS format (e.g., 20250705103000).',
        ];
    }
}
