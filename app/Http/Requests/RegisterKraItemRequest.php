<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\KraDevice;

class RegisterKraItemRequest extends FormRequest
{
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

    public function rules(): array
    {
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