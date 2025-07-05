<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ApiClient;
use App\Models\KraDevice;

class ProcessTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ApiClient $apiClient */
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

        // Ensure the device is activated before allowing transactions
        if ($kraDevice->status !== 'ACTIVATED') {
            throw new \Illuminate\Auth\Access\AuthorizationException('KRA Device is not active or initialized.');
        }

        $this->attributes->set('_kra_device_model', $kraDevice);
        return true;
    }

    public function rules(): array
    {
        return [
            'gatewayDeviceId' => ['required', 'uuid', Rule::exists('kra_devices', 'id')],
            'taxpayerPin' => ['required', 'string', 'max:20', Rule::exists('taxpayer_pins', 'pin')],
            'receiptType' => ['required', 'string', Rule::in(['NORMAL', 'COPY', 'TRAINING', 'PROFORMA'])],
            'transactionType' => ['required', 'string', Rule::in(['SALE', 'CREDIT_NOTE', 'DEBIT_NOTE'])],
            'internalReceiptNumber' => ['required', 'string', 'max:50'],

            'buyerPin' => ['nullable', 'string', 'max:20'],
            'saleLocationAddress' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.taxDesignationCode' => ['required', 'string', 'max:5'],
            'items.*.discountAmount' => ['nullable', 'numeric', 'min:0'],
            'items.*.packagingUnitCode' => ['nullable', 'string', 'max:10'],
            'items.*.packagingQuantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantityUnitCode' => ['nullable', 'string', 'max:10'],
            'items.*.supplierPrice' => ['nullable', 'numeric', 'min:0'],
            'items.*.discountRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.taxableAmount' => ['nullable', 'numeric'],
            'items.*.taxAmount' => ['nullable', 'numeric'],
            'items.*.totalAmount' => ['nullable', 'numeric'],

            'totalAmount' => ['required', 'numeric', 'min:0'],
            'paymentMethod' => ['required', 'string', 'max:50'],

            'taxRates' => ['required', 'array'],
            'taxRates.A' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxRates.B' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxRates.C' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxRates.D' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'taxRates.E' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'taxableAmounts' => ['required', 'array'],
            'taxableAmounts.A' => ['nullable', 'numeric', 'min:0'],
            'taxableAmounts.B' => ['nullable', 'numeric', 'min:0'],
            'taxableAmounts.C' => ['nullable', 'numeric', 'min:0'],
            'taxableAmounts.D' => ['nullable', 'numeric', 'min:0'],
            'taxableAmounts.E' => ['nullable', 'numeric', 'min:0'],

            'calculatedTaxes' => ['required', 'array'],
            'calculatedTaxes.A' => ['nullable', 'numeric', 'min:0'],
            'calculatedTaxes.B' => ['nullable', 'numeric', 'min:0'],
            'calculatedTaxes.C' => ['nullable', 'numeric', 'min:0'],
            'calculatedTaxes.D' => ['nullable', 'numeric', 'min:0'],
            'calculatedTaxes.E' => ['nullable', 'numeric', 'min:0'],

            'originalInvoiceScuId' => ['nullable', 'string', 'max:50', Rule::requiredIf($this->input('transactionType') === 'CREDIT_NOTE')],
            'originalInvoiceInternalReceiptNumber' => ['nullable', 'string', 'max:50', Rule::requiredIf($this->input('transactionType') === 'CREDIT_NOTE')],
            'creditNoteReason' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('transactionType') === 'CREDIT_NOTE')],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $items = $this->input('items');
            $expectedTotalAmount = 0;
            $expectedTaxableAmounts = array_fill_keys(['A', 'B', 'C', 'D', 'E'], 0.00);
            $expectedCalculatedTaxes = array_fill_keys(['A', 'B', 'C', 'D', 'E'], 0.00);

            $providedTaxRates = $this->input('taxRates');

            foreach ($items as $item) {
                $itemTotalBeforeTax = ($item['quantity'] * $item['unitPrice']) - ($item['discountAmount'] ?? 0);
                $expectedTotalAmount += $itemTotalBeforeTax;

                $taxType = $item['taxDesignationCode'];
                $taxRate = $providedTaxRates[$taxType] ?? 0.00;

                $itemTaxableAmount = $itemTotalBeforeTax;
                $itemCalculatedTax = round($itemTaxableAmount * ($taxRate / 100), 2);

                $expectedTaxableAmounts[$taxType] += $itemTaxableAmount;
                $expectedCalculatedTaxes[$taxType] += $itemCalculatedTax;

                $expectedTotalAmount += $itemCalculatedTax;
            }

            $epsilon = 0.01;

            if (abs($expectedTotalAmount - $this->input('totalAmount')) > $epsilon) {
                $validator->errors()->add('totalAmount', 'Calculated total amount does not match provided total. Expected: ' . number_format($expectedTotalAmount, 2) . ', Provided: ' . number_format($this->input('totalAmount'), 2));
            }

            foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
                if (abs(($expectedTaxableAmounts[$label] ?? 0) - ($this->input('taxableAmounts')[$label] ?? 0)) > $epsilon) {
                    $validator->errors()->add("taxableAmounts.{$label}", "Calculated taxable amount for {$label} does not match provided.");
                }
                if (abs(($expectedCalculatedTaxes[$label] ?? 0) - ($this->input('calculatedTaxes')[$label] ?? 0)) > $epsilon) {
                    $validator->errors()->add("calculatedTaxes.{$label}", "Calculated tax for {$label} does not match provided.");
                }
            }

            if ($this->input('transactionType') === 'CREDIT_NOTE') {
                foreach ($items as $index => $item) {
                    if ($item['quantity'] > 0 || ($item['unitPrice'] > 0 && ($item['totalAmount'] ?? 0) > 0)) {
                        $validator->errors()->add("items.{$index}.quantity", "For credit notes, quantity and amounts must be negative.");
                    }
                }
                if ($this->input('totalAmount') > 0) {
                     $validator->errors()->add('totalAmount', 'For credit notes, total amount must be negative.');
                }
            }
        });
    }
} 