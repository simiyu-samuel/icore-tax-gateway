<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessTransactionRequest;
use App\Models\KraDevice;
use App\Services\KraSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KraSalesController extends Controller
{
    protected KraSalesService $kraSalesService;

    public function __construct(KraSalesService $kraSalesService)
    {
        $this->kraSalesService = $kraSalesService;
    }

    /**
     * Processes a sales or credit note transaction.
     * POST /api/v1/transactions
     * @param ProcessTransactionRequest $request
     * @return JsonResponse
     */
    public function process(ProcessTransactionRequest $request): JsonResponse
    {
        /** @var KraDevice $_kraDeviceModel */
        $_kraDeviceModel = $request->attributes->get('_kra_device_model'); // Access merged model

        $transactionData = $request->validated();

        try {
            /** @var Transaction $transaction */
            $transaction = $this->kraSalesService->processTransaction($_kraDeviceModel, $transactionData);

            $responseForPos = [
                "status" => 200,
                "statusCode" => "SUCCESS",
                "message" => "Invoice created successfully",
                "data" => [
                    "invoiceNo" => $transaction->kra_cu_invoice_number,
                    "traderInvoiceNo" => $transaction->internal_receipt_number,
                    "totalAmount" => $transactionData['totalAmount'] ?? null,
                    "totalTaxableAmount" => isset($transactionData['taxableAmounts']) ? array_sum($transactionData['taxableAmounts']) : null,
                    "totalTaxAmount" => isset($transactionData['calculatedTaxes']) ? array_sum($transactionData['calculatedTaxes']) : null,
                    "paymentType" => $transactionData['paymentMethod'] ?? null,
                    "salesTypeCode" => $transactionData['receiptType'] ?? null,
                    "receiptTypeCode" => $transaction->kra_receipt_label,
                    "salesStatusCode" => ($transaction->journal_status === 'COMPLETED' ? '01' : '00'),
                    "salesDate" => $transaction->kra_timestamp ? $transaction->kra_timestamp->format('YmdHis') : null,
                    "currency" => "KES",
                    "internalData" => $transaction->kra_internal_data,
                    "signature" => $transaction->kra_digital_signature,
                    "scdcId" => $transaction->kra_scu_id,
                    "scuReceiptDate" => $transaction->kra_timestamp ? $transaction->kra_timestamp->format('YmdHis') : null,
                    "scuReceiptNo" => $transaction->response_payload['TNumber'] ?? null,
                    "invoiceVerificationUrl" => $transaction->kra_qr_code_url,
                    "exchangeRate" => 1,
                    "salesItems" => array_map(function($item, $index) use ($transactionData) {
                        return [
                            "itemCode" => $item['itemCode'] ?? null,
                            "qty" => $item['quantity'] ?? null,
                            "pkg" => $item['packagingQuantity'] ?? 0,
                            "unitPrice" => $item['unitPrice'] ?? null,
                            "amount" => $item['totalAmount'] ?? (($item['quantity'] ?? 0) * ($item['unitPrice'] ?? 0)),
                            "discountAmount" => $item['discountAmount'] ?? 0,
                            "itemSeq" => $index + 1,
                            "itemClassCode" => $item['itemClassificationCode'] ?? null,
                            "taxCode" => $item['taxDesignationCode'] ?? null,
                            "taxAmount" => $item['taxAmount'] ?? 0,
                            "taxableAmount" => $item['taxableAmount'] ?? 0,
                            "qtyCode" => $item['quantityUnitCode'] ?? 'U',
                            "pkgCode" => $item['packagingUnitCode'] ?? 'PC',
                            "itemId" => null,
                            "name" => $item['description'] ?? $item['itemName'] ?? null,
                            "discountRate" => $item['discountRate'] ?? 0,
                            "discount" => $item['discountAmount'] ?? 0,
                        ];
                    }, $transactionData['items'], array_keys($transactionData['items'])),
                    "customerPin" => $transactionData['buyerPin'] ?? "",
                    "customerName" => $transactionData['customerName'] ?? "WALKINGINCUSTOMER",
                ]
            ];

            return response()->json($responseForPos);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA transaction processing: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
}
