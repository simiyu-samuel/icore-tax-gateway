<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\KraDevice;

class SendKraPurchaseRequest extends FormRequest
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
        // Rules based on KRA documentation (Doc 1, Sections 24.8.4 & 24.8.6 - Pages 22 & 24)
        return [
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],

            // Purchase Header Fields (SEND_PURCHASE)
            'invoiceId' => ['required', 'string', 'max:50'], // InvId
            'branchId' => ['required', 'string', 'max:10'], // bhfId
            'supplierPin' => ['required', 'string', 'max:20'], // bencId
            'supplierName' => ['required', 'string', 'max:255'], // bcncNm
            'supplierCuId' => ['required', 'string', 'max:50'], // bencSdcId
            'registrationTypeCode' => ['required', 'string', Rule::in(['M', 'A'])], // regTyCd - M: Manual, A: Automatic
            'referenceId' => ['required', 'string', 'max:50'], // refId (Supplier invoice number)
            'paymentTypeCode' => ['required', 'string', 'max:10'], // payTyCd
            'invoiceStatusCode' => ['required', 'string', 'max:10'], // invStatusCd
            'transactionDate' => ['required', 'date_format:Ymd'], // ocde - YYYYMMDD
            'validDate' => ['nullable', 'date_format:Ymd'], // validDt
            'cancelRequestDate' => ['nullable', 'date_format:YmdHis'], // cancelReqDt - YYYYMMDDHHMISS
            'cancelDate' => ['nullable', 'date_format:YmdHis'], // cancelDt
            'refundDate' => ['nullable', 'date_format:YmdHis'], // refundDt
            'cancelTypeCode' => ['nullable', 'string', 'max:10'], // cancelTyCd
            'totalTaxableAmountA' => ['nullable', 'numeric', 'min:0'], // totTaxablAmtA
            'totalTaxableAmountB' => ['nullable', 'numeric', 'min:0'], // totTaxablAmtB
            'totalTaxableAmountC' => ['nullable', 'numeric', 'min:0'], // totTaxablAmtC
            'totalTaxableAmountD' => ['nullable', 'numeric', 'min:0'], // totTaxablAmtD
            'totalTaxableAmountE' => ['nullable', 'numeric', 'min:0'], // totTaxablAmtE
            'totalTaxA' => ['nullable', 'numeric', 'min:0'], // totTaxA
            'totalTaxB' => ['nullable', 'numeric', 'min:0'], // totTaxB
            'totalTaxC' => ['nullable', 'numeric', 'min:0'], // totTaxC
            'totalTaxD' => ['nullable', 'numeric', 'min:0'], // totTaxD
            'totalTaxE' => ['nullable', 'numeric', 'min:0'], // totTaxE
            'totalSupplierPrice' => ['required', 'numeric', 'min:0'], // totSplpc
            'totalTax' => ['required', 'numeric', 'min:0'], // totTax
            'totalAmount' => ['required', 'numeric', 'min:0'], // totAmt
            'remark' => ['nullable', 'string', 'max:255'], // remark
            'registerUserId' => ['required', 'string', 'max:50'], // regusrId
            'registerDate' => ['required', 'date_format:YmdHis'], // regDt - YYYYMMDDHHMISS

            // Purchase Item Fields (SEND_PURCHASEITEM) - array of items
            'items' => ['required', 'array', 'min:1'],
            'items.*.sequence' => ['required', 'integer', 'min:1'], // itemSeq
            'items.*.itemClassificationCode' => ['required', 'string', 'max:50'], // itemClsCd
            'items.*.itemCode' => ['required', 'string', 'max:50'], // itemCd
            'items.*.itemName' => ['required', 'string', 'max:255'], // itemNm
            'items.*.supplierItemClassificationCode' => ['required', 'string', 'max:50'], // bcncItemClsCd
            'items.*.supplierItemCode' => ['required', 'string', 'max:50'], // bcncItemCd
            'items.*.supplierItemName' => ['required', 'string', 'max:255'], // bcncItemNm
            'items.*.packagingUnitCode' => ['required', 'string', 'max:10'], // pkgUnitCd
            'items.*.packagingQuantity' => ['required', 'numeric', 'min:0'], // pkgQty
            'items.*.quantityUnitCode' => ['required', 'string', 'max:10'], // qtyUnitCd
            'items.*.quantity' => ['required', 'numeric', 'min:0'], // qty
            'items.*.expiryDate' => ['nullable', 'date_format:Ymd'], // expirDt - YYYYMMDD
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'], // untpc
            'items.*.supplierPrice' => ['required', 'numeric', 'min:0'], // splpc
            'items.*.discountRate' => ['required', 'numeric', 'min:0', 'max:100'], // dcRate
            'items.*.discountAmount' => ['required', 'numeric', 'min:0'], // dcAmt
            'items.*.taxableAmount' => ['required', 'numeric'], // taxablAmt
            'items.*.taxType' => ['required', 'string', 'max:10'], // taxTyCd (e.g., 'B')
            'items.*.taxAmount' => ['required', 'numeric'], // tax
        ];
    }

    public function messages(): array
    {
        return [
            'gatewayDeviceId.exists' => 'The provided Gateway Device ID does not exist or is not linked to the given Taxpayer PIN.',
            'taxpayerPin.exists' => 'The provided Taxpayer PIN does not exist in our system or is inactive.',
            'registrationTypeCode.in' => 'Registration type must be either "M" (Manual) or "A" (Automatic).',
            'items.required' => 'At least one item is required for the purchase.',
            'items.min' => 'At least one item is required for the purchase.',
        ];
    }
}
