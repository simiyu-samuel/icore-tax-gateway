<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\KraApi;
use App\Exceptions\KraApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class ProcessKraJournalingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout for journaling
    public $tries = 3; // Retry up to 3 times
    public $backoff = [60, 300, 600]; // Retry delays: 1min, 5min, 10min

    protected string $transactionId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $transactionId)
    {
        $this->transactionId = $transactionId;
        $this->onQueue('kra_journaling');
    }

    /**
     * Execute the job.
     * 
     * This job handles the ASYNCHRONOUS journaling process:
     * 1. Sends SEND_RECEIPTITEM for each item in the transaction
     * 2. Sends final SEND_RECEIPT for the complete journal
     * 
     * Maps to Doc 1, Section 21.8 (Data Flow Transmission)
     */
    public function handle(KraApi $kraApi): void
    {
        $transaction = Transaction::findOrFail($this->transactionId);
        
        // Update status to QUEUED
        $transaction->update(['journal_status' => 'QUEUED']);

        try {
            Log::info("Starting KRA journaling for transaction: {$this->transactionId}");

            $kraDevice = $transaction->kraDevice;
            $pin = $kraDevice->taxpayerPin->pin;
            $requestPayload = $transaction->request_payload;

            // Step 1: Send SEND_RECEIPTITEM for each item in the transaction
            if (!empty($requestPayload['items'])) {
                foreach ($requestPayload['items'] as $itemIndex => $item) {
                    $this->sendReceiptItem($kraApi, $pin, $transaction, $item, $itemIndex);
                }
            }

            // Step 2: Send final SEND_RECEIPT for complete journal
            $this->sendReceiptJournal($kraApi, $pin, $transaction);

            // Update status to COMPLETED
            $transaction->update(['journal_status' => 'COMPLETED']);
            
            Log::info("KRA journaling completed successfully for transaction: {$this->transactionId}");

        } catch (\Exception $e) {
            Log::error("KRA journaling failed for transaction: {$this->transactionId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update status to FAILED
            $transaction->update([
                'journal_status' => 'FAILED',
                'journal_error_message' => $e->getMessage()
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Send SEND_RECEIPTITEM command for a single item.
     * 
     * @param KraApi $kraApi
     * @param string $pin
     * @param Transaction $transaction
     * @param array $item
     * @param int $itemIndex
     */
    private function sendReceiptItem(KraApi $kraApi, string $pin, Transaction $transaction, array $item, int $itemIndex): void
    {
        $itemNumber = $itemIndex + 1; // KRA expects 1-based item numbers
        $receiptItemPayload = [
            'RNum' => $transaction->internal_receipt_number,
            'ItemNo' => $itemNumber,
            'ItemName' => $item['name'] ?? 'Unknown Item',
            'ItemCode' => $item['code'] ?? '',
            'ItemQuantity' => $item['quantity'] ?? 1,
            'ItemPrice' => number_format($item['unitPrice'] ?? 0, 2, '.', ''),
            'ItemTotal' => number_format($item['totalAmount'] ?? 0, 2, '.', ''),
        ];

        // Add tax information if available
        if (!empty($item['taxRate'])) {
            $receiptItemPayload['ItemTaxRate'] = number_format($item['taxRate'], 2, '.', '');
        }
        if (!empty($item['taxAmount'])) {
            $receiptItemPayload['ItemTaxAmount'] = number_format($item['taxAmount'], 2, '.', '');
        }

        $xmlPayload = KraApi::buildKraXml($pin, 'SEND_RECEIPTITEM', $receiptItemPayload);
        
        // Send to KRA's central API (not device)
        $response = $kraApi->sendCommand('/api/journal/receipt-item', $xmlPayload, false);
        
        $itemNumber = $itemIndex + 1;
        Log::info("SEND_RECEIPTITEM sent for transaction: {$transaction->id}, item: {$itemNumber}");
    }

    /**
     * Send final SEND_RECEIPT command for complete journal.
     * 
     * @param KraApi $kraApi
     * @param string $pin
     * @param Transaction $transaction
     */
    private function sendReceiptJournal(KraApi $kraApi, string $pin, Transaction $transaction): void
    {
        $requestPayload = $transaction->request_payload;
        
        // Prepare journal payload (similar to initial signing but for central API)
        $journalPayload = [
            'RNum' => $transaction->internal_receipt_number,
            'Rtype' => $this->getKraReceiptType($transaction->receipt_type),
            'TType' => $this->getKraTransactionType($transaction->transaction_type),
            'Date' => $transaction->kra_timestamp->format('d/m/Y'),
            'Time' => $transaction->kra_timestamp->format('H:i:s'),
            'SNumber' => $transaction->kra_scu_id,
            'Signature' => $transaction->kra_digital_signature,
            'InternalData' => $transaction->kra_internal_data,
        ];

        // Add tax totals
        if (!empty($requestPayload['taxRates'])) {
            $taxRates = $this->formatTaxDataForKraXml($requestPayload['taxRates'], 'Rate');
            $journalPayload = array_merge($journalPayload, $taxRates);
        }
        if (!empty($requestPayload['taxableAmounts'])) {
            $taxableAmounts = $this->formatTaxDataForKraXml($requestPayload['taxableAmounts'], 'Amount');
            $journalPayload = array_merge($journalPayload, $taxableAmounts);
        }
        if (!empty($requestPayload['calculatedTaxes'])) {
            $calculatedTaxes = $this->formatTaxDataForKraXml($requestPayload['calculatedTaxes'], 'Tax');
            $journalPayload = array_merge($journalPayload, $calculatedTaxes);
        }

        $xmlPayload = KraApi::buildKraXml($pin, 'SEND_RECEIPT', $journalPayload);
        
        // Send to KRA's central API
        $response = $kraApi->sendCommand('/api/journal/receipt', $xmlPayload, false);
        
        Log::info("SEND_RECEIPT journal sent for transaction: {$transaction->id}");
    }

    /**
     * Helper to format tax data for KRA XML payload.
     * @param array $taxData E.g., ['A' => 0.00, 'B' => 16.00] for rates, or ['B' => 100.00] for amounts
     * @param string $typeSuffix 'Rate', 'Amount', or 'Tax'
     * @return array
     */
    private function formatTaxDataForKraXml(array $taxData, string $typeSuffix): array
    {
        $formatted = [];
        $taxLabels = ['A', 'B', 'C', 'D', 'E']; // As per Doc 1, Section 6.20.4

        foreach ($taxLabels as $index => $label) {
            // KRA wants TaxRate1, TaxRate2... corresponding to A, B...
            // Our internal designation 'A', 'B' maps to their index + 1
            $kraKeyNumber = $index + 1;
            $kraKey = "Tax{$typeSuffix}{$kraKeyNumber}";
            $value = $taxData[$label] ?? 0.00; // Default to 0.00 if label not present

            $formatted[$kraKey] = number_format($value, 2, '.', ''); // Ensure 2 decimal places, dot separator
        }
        return $formatted;
    }

    /**
     * Maps our internal receipt type to KRA's single character code.
     * @param string $type
     * @return string
     */
    private function getKraReceiptType(string $type): string
    {
        return match (strtoupper($type)) {
            'NORMAL' => 'N',
            'COPY' => 'C',
            'TRAINING' => 'T',
            'PROFORMA' => 'P',
            default => throw new \InvalidArgumentException("Invalid receipt type: {$type}"),
        };
    }

    /**
     * Maps our internal transaction type to KRA's single character code.
     * @param string $type
     * @return string
     */
    private function getKraTransactionType(string $type): string
    {
        return match (strtoupper($type)) {
            'SALE' => 'S',
            'CREDIT_NOTE' => 'NC', // Normal Credit Note
            'DEBIT_NOTE' => 'ND', // Assuming ND for Normal Debit Note, check KRA spec if different.
            default => throw new \InvalidArgumentException("Invalid transaction type: {$type}"),
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $transaction = Transaction::find($this->transactionId);
        
        if ($transaction) {
            $transaction->update([
                'journal_status' => 'FAILED',
                'journal_error_message' => $exception->getMessage()
            ]);
        }

        Log::error("KRA journaling job failed permanently for transaction: {$this->transactionId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
} 