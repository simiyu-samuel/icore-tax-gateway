<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\TaxpayerPin;

class InitializeKraDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var ApiClient $apiClient */
        $apiClient = $this->attributes->get('api_client');

        if (!$apiClient || !$apiClient->is_active) {
            return false;
        }

        $requestedPin = $this->input('taxpayerPin');
        if (empty($requestedPin)) {
            return false;
        }

        $taxpayerPinModel = TaxpayerPin::where('pin', $requestedPin)->first();

        if (!$taxpayerPinModel || !in_array($requestedPin, explode(',', $apiClient->allowed_taxpayer_pins))) {
            return false;
        }

        $this->merge(['_taxpayer_pin_model' => $taxpayerPinModel]);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
            'branchOfficeId' => ['required', 'string', 'max:10'],
            'deviceType' => ['required', 'string', Rule::in(['OSCU', 'VSCU'])],
            'deviceSerialNumber' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'taxpayerPin.exists' => 'The provided taxpayer PIN does not exist in our system or is inactive.',
            'deviceType.in' => 'The device type must be either OSCU or VSCU.',
        ];
    }
}
