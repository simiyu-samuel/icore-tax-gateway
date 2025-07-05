<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\KraDevice;

class SendKraInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $apiClient = $this->attributes->get('api_client');

        if (!$apiClient || !$apiClient->is_active) {
            return false;
        }

        $gatewayDeviceId = $this->input('gatewayDeviceId');
        $taxpayerPin = $this->input('taxpayerPin');

        $kraDevice = KraDevice::where('id', $gatewayDeviceId)
                              ->whereHas('taxpayerPin', function($query) use ($taxpayerPin) {
                                  $query->where('pin', $taxpayerPin);
                              })
                              ->first();

        $allowedPins = explode(',', $apiClient->allowed_taxpayer_pins);
        if (!in_array($taxpayerPin, $allowedPins)) {
            return false;
        }

        if (!$kraDevice) {
            return false;
        }

        $this->attributes->set('_kra_device_model', $kraDevice);
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Rules based on KRA documentation (Doc 1, Section 24.8.7, Page 24)
        return [
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],

            // Inventory Movement Fields (SEND_INVENTORY)
            'branchId' => ['required', 'string', 'max:10'], // bhfId
            'itemClassificationCode' => ['required', 'string', 'max:50'], // itemClsCd
            'itemCode' => ['required', 'string', 'max:50'], // itemCd
            'quantity' => ['required', 'numeric'], // qty (can be positive for stock in, negative for stock out)
            'updateDate' => ['required', 'date_format:YmdHis'], // updDt - YYYYMMDDHHMMSS format
            'movementType' => ['required', 'string', Rule::in(['IN', 'OUT'])], // Movement type: IN for stock in, OUT for stock out
            'reason' => ['nullable', 'string', 'max:255'], // Optional reason for movement
            'referenceId' => ['nullable', 'string', 'max:50'], // Optional reference ID
        ];
    }

    public function messages(): array
    {
        return [
            'gatewayDeviceId.exists' => 'The provided Gateway Device ID does not exist or is not linked to the given Taxpayer PIN.',
            'taxpayerPin.exists' => 'The provided Taxpayer PIN does not exist in our system or is inactive.',
            'movementType.in' => 'Movement type must be either "IN" (stock in) or "OUT" (stock out).',
            'updateDate.date_format' => 'Update date must be in YYYYMMDDHHMMSS format.',
            'quantity.numeric' => 'Quantity must be a valid number.',
        ];
    }

    /**
     * Get the validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure quantity is properly formatted for KRA
        if (isset($validated['quantity'])) {
            $validated['quantity'] = (float) $validated['quantity'];
        }

        // Ensure updateDate is in correct format
        if (isset($validated['updateDate'])) {
            $validated['updateDate'] = date('YmdHis', strtotime($validated['updateDate']));
        }

        return $validated;
    }
}
