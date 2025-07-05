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
            $transaction = $this->kraSalesService->processTransaction($_kraDeviceModel, $transactionData);

            return response()->json([
                'message' => 'Transaction processed and signed successfully.',
                'gatewayTransactionId' => $transaction->id,
                'kra' => [ // Data returned from KRA for the POS/ERP to print
                    'scuId' => $transaction->kra_scu_id,
                    'scuTimestamp' => $transaction->kra_timestamp->toISOString(),
                    'receiptLabel' => $transaction->kra_receipt_label,
                    // 'receiptCounterPerType' and 'totalReceiptCounter' if available in RECV_RECEIPT and stored
                    'digitalSignature' => $transaction->kra_digital_signature,
                    'internalData' => $transaction->kra_internal_data,
                    'cuInvoiceNumber' => $transaction->kra_cu_invoice_number,
                    'qrCodeUrl' => $transaction->kra_qr_code_url,
                ],
                'journalStatus' => $transaction->journal_status, // Show initial status of async journaling
            ]);
        } catch (\App\Exceptions\KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("Unexpected error during KRA transaction processing: " . $e->getMessage(), ['exception' => $e, 'trace_id' => $request->attributes->get('traceId')]);
            throw $e;
        }
    }
}
