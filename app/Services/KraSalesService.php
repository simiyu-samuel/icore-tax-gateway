<?php

namespace App\Services;

use App\Models\KraDevice;
use App\Models\Transaction;
use App\Jobs\ProcessKraJournalingJob;
use App\Exceptions\KraApiException;
use SimpleXMLElement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KraSalesService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Processes a sales or credit note transaction.
     * This method handles the SYNCHRONOUS part: sending to OSCU/VSCU for signing
     * and storing the signed response.
     * The ASYNCHRONOUS part (journaling) is dispatched as a job.
     *
     * Maps to CMD: SEND_RECEIPT (Doc 1, Section 21.7.1, Page 15).
     *
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @param array $transactionData The transaction data (invoice/credit note) from the API request.
     * @return Transaction The newly created and signed transaction record in our database.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function processTransaction(KraDevice $kraDevice, array $transactionData): Transaction
    {
        $pin = $kraDevice->taxpayerPin->pin;

        // Determine the base URL for the direct device communication (VSCU local JAR or KRA OSCU endpoint)
        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url'); // For OSCU, this might be a specific endpoint just for SEND_RECEIPT

        $originalBaseUrl = $this->kraApi->baseUrl;
        $this->kraApi->baseUrl = $targetBaseUrl;

        // Prepare the XML payload for CMD: SEND_RECEIPT
        // Data fields based on Doc 1, Section 21.7.1, Page 15.
        // Example for tax rates (TaxRate1-4) and amounts (Amount1-4, Tax1-4)
        // Assuming transactionData contains these as 'taxRates' and 'taxAmounts' keyed by KRA's internal designation (e.g., 'B' for 16%)
        $taxRates = $this->formatTaxDataForKraXml($transactionData['taxRates'] ?? [], 'Rate');
        $taxableAmounts = $this->formatTaxDataForKraXml($transactionData['taxableAmounts'] ?? [], 'Amount');
        $calculatedTaxes = $this->formatTaxDataForKraXml($transactionData['calculatedTaxes'] ?? [], 'Tax');

        $kraReceiptPayload = [
            'Rtype' => $this->getKraReceiptType($transactionData['receiptType']),       // N, C, T, P
            'TType' => $this->getKraTransactionType($transactionData['transactionType']), // S, NC
            'Date' => now()->format('d/m/Y'), // DD/MM/YYYY
            'Time' => now()->format('H:i:s'), // HH:MM:SS
            'RNum' => $transactionData['internalReceiptNumber'], // TIS's internal receipt number
        ];

        // Add formatted tax data to payload
        $kraReceiptPayload = array_merge($kraReceiptPayload, $taxRates, $taxableAmounts, $calculatedTaxes);

        // Add optional ClientsPin
        if (!empty($transactionData['buyerPin'])) {
            $kraReceiptPayload['ClientsPin'] = $transactionData['buyerPin'];
        }
        // Add optional Clientphone if available and needed by KRA for SEND_RECEIPT
        // if (!empty($transactionData['buyerPhone'])) {
        //     $kraReceiptPayload['Clientphone'] = $transactionData['buyerPhone'];
        // }

        $xmlPayload = KraApi::buildKraXml($pin, 'SEND_RECEIPT', $kraReceiptPayload);

        $endpointPath = ''; // KRA CMD:SEND_RECEIPT is often sent directly to device URL, no path.
                           // If OSCU needs a specific path (e.g., /api/sendReceipt), update here.

        try {
            // Send the command with STRICT timeout (1000ms) as this is real-time signing
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload, true);

            $parsedXml = simplexml_load_string($response->body());
            $dataNode = $parsedXml->DATA ?? null;

            if (!$dataNode) {
                throw new \Exception("KRA SEND_RECEIPT response missing DATA node: " . $response->body());
            }

            // Parse response data (Doc 1, Section 5.3 and 21.7.2 for RECV_RECEIPT)
            $kraScuId = (string) ($dataNode->Snumber ?? null);
            $kraTimestamp = (string) ($dataNode->Date . ' ' . $dataNode->Time ?? null); // Format "DD/MM/YYYY HH:MM:SS"
            $kraReceiptLabel = (string) ($dataNode->RLabel ?? null);
            $kraReceiptCounterPerType = (int) ($dataNode->TNumber ?? 0);
            $kraTotalReceiptCounter = (int) ($dataNode->GNumber ?? 0);
            $kraDigitalSignature = (string) ($dataNode->Signature ?? null);
            $kraInternalData = (string) ($dataNode->InternalData ?? null);

            // Construct CU Invoice Number: {SCU ID}/Receipt Number (Doc 1, Section 6.23.4)
            $kraCuInvoiceNumber = "{$kraScuId}/{$transactionData['internalReceiptNumber']}";

            // Construct QR Code URL (Doc 1, Section 6.23.8, Page 7)
            // Format: invoice_date(ddmmyyyy)#time(hhmmss)#cu_number#cu_receipt_number#internal_data#receipt_signature
            $qrDate = \DateTime::createFromFormat('d/m/Y H:i:s', $kraTimestamp)->format('dmY');
            $qrTime = \DateTime::createFromFormat('d/m/Y H:i:s', $kraTimestamp)->format('Hi');
            $qrCodeData = implode('#', [
                $qrDate,
                $qrTime,
                $kraScuId,
                $kraReceiptCounterPerType, // KRA uses receipt counter per type for QR code
                $kraInternalData,
                $kraDigitalSignature
            ]);
            // KRA's actual QR code URL structure from Doc 1, Page 8 example:
            // https://etims.kra.go.ke/common/link/etims/receipt/indexEtimsReceptData?{KRA-PIN+BHF-ID+RcpSignture)
            // This seems to imply different data structure, we will use the one based on 6.23.8 for consistency.
            // If the QR URL is dynamic, KRA's API might return it directly. Otherwise, this is generated.
            $kraQrCodeUrl = config('kra.qr_code_base_url') . "?data=" . urlencode($qrCodeData); // Assuming a base URL for QR validation


            // Create a record in our local 'transactions' table
            $transaction = Transaction::create([
                'id' => (string) Str::uuid(),
                'kra_device_id' => $kraDevice->id,
                'taxpayer_pin_id' => $kraDevice->taxpayer_pin_id,
                'internal_receipt_number' => $transactionData['internalReceiptNumber'],
                'receipt_type' => $transactionData['receiptType'],
                'transaction_type' => $transactionData['transactionType'],
                'kra_scu_id' => $kraScuId,
                'kra_receipt_label' => $kraReceiptLabel,
                'kra_cu_invoice_number' => $kraCuInvoiceNumber,
                'kra_digital_signature' => $kraDigitalSignature,
                'kra_internal_data' => $kraInternalData,
                'kra_qr_code_url' => $kraQrCodeUrl,
                'request_payload' => $transactionData, // Store original request JSON
                'response_payload' => $response->json(), // Store KRA response JSON (if available, or array from parsed XML)
                'raw_kra_request_xml' => $xmlPayload->asXML(), // Store raw XML sent
                'raw_kra_response_xml' => $response->body(), // Store raw XML received
                'journal_status' => 'PENDING', // Initially pending for async job
                'kra_timestamp' => \DateTime::createFromFormat('d/m/Y H:i:s', $kraTimestamp),
            ]);

            // --- ASYNCHRONOUS PART: Dispatch job for journaling ---
            // Doc 1, Section 5.5 and 6.18 says TIS sends complete journal data.
            // Doc 1, Section 21.8 says data flow transmission should be max 15 min.
            // This will use CMD: SEND_RECEIPTITEM for each item, and potentially another SEND_RECEIPT for full journal.
            ProcessKraJournalingJob::dispatch($transaction->id)->onQueue('kra_journaling');

            return $transaction;

        } catch (KraApiException $e) {
            // Handle KraApiException specifically for the synchronous call
            // Example: If KRA device is offline, record the error and don't create Transaction
            Log::error("KRA_SIGNING_ERROR: " . $e->getMessage(), ['trace_id' => request()->attributes->get('traceId'), 'kra_error' => $e->kraErrorCode, 'raw_response' => $e->kraRawResponse]);
            throw $e; // Re-throw to be caught by global error handler
        } finally {
            // Restore original base URL after the command execution
            $this->kraApi->baseUrl = $originalBaseUrl;
        }
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
            $kraKey = "Tax{$typeSuffix}" . ($index + 1);
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
} 