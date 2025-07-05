<?php
namespace App\Services;
use App\Models\KraDevice;
use App\Exceptions\KraApiException;
use SimpleXMLElement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class KraReportService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Retrieves an X Daily Report from KRA.
     * Maps to KRA's X daily report (Section 15.1, Doc 1, Page 12).
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @return array Parsed report data.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function getXDailyReport(KraDevice $kraDevice): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        // KRA X daily report command (check KRA spec for exact CMD string)
        $xmlPayload = KraApi::buildKraXml($pin, 'X_REPORT'); // Assuming 'X_REPORT' is the CMD
        // KRA's X report doesn't typically take specific date range in DATA for the request itself,
        // it's defined as "since the end of the previous Z daily report".

        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $endpointPath = '/api/getXReport'; // Example: A common path for this command on KRA's API (check actual KRA docs)

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        try {
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload);
            $parsedXml = simplexml_load_string($response->body());

            $dataNode = $parsedXml->DATA ?? null;
            if (!$dataNode) {
                throw new \Exception("KRA X Report response missing DATA node: " . $response->body());
            }

            // Parse the detailed report structure from DATA node
            // (Doc 1, Section 15.1, Page 12 outlines fields)
            $report = [
                'tradeName' => (string) ($dataNode->tradeName ?? null),
                'taxpayerPin' => (string) ($dataNode->PIN ?? null),
                'reportDate' => (string) ($dataNode->date ?? null), // DD/MM/YYYY
                'reportTime' => (string) ($dataNode->time ?? null), // HH:MM:SS
                'reportType' => (string) ($dataNode->reportType ?? 'X_DAILY_REPORT'),
                'totalSalesAmountNS' => (float) ($dataNode->totalSalesAmountNS ?? 0.0),
                'numberOfSalesReceiptsNS' => (int) ($dataNode->numberOfSalesReceiptsNS ?? 0),
                'totalCreditNoteAmountNC' => (float) ($dataNode->totalCreditNoteAmountNC ?? 0.0),
                'numberOfCreditNotesNC' => (int) ($dataNode->numberOfCreditNotesNC ?? 0),
                // ... parse all 20 fields as per KRA Doc 1, Section 15.1
                // For example:
                // 'taxableAmountsPerRate' => [
                //     'A' => (float) ($dataNode->taxableAmountA ?? 0),
                //     'B' => (float) ($dataNode->taxableAmountB ?? 0),
                // ],
                // 'taxAmountsPerRate' => [
                //     'A' => (float) ($dataNode->taxAmountA ?? 0),
                //     'B' => (float) ($dataNode->taxAmountB ?? 0),
                // ],
                // 'openingDeposit' => (float) ($dataNode->openingDeposit ?? 0),
                // 'numberOfItemsSold' => (int) ($dataNode->numberOfItemsSold ?? 0),
                // 'numberOfReceiptCopies' => (int) ($dataNode->numberOfReceiptCopies ?? 0),
                // 'totalReceiptCopiesAmount' => (float) ($dataNode->totalReceiptCopiesAmount ?? 0),
                // 'numberOfTrainingReceipts' => (int) ($dataNode->numberOfTrainingReceipts ?? 0),
                // 'totalTrainingReceiptsAmount' => (float) ($dataNode->totalTrainingReceiptsAmount ?? 0),
                // 'numberOfProformaReceipts' => (int) ($dataNode->numberOfProformaReceipts ?? 0),
                // 'totalProformaReceiptsAmount' => (float) ($dataNode->totalProformaReceiptsAmount ?? 0),
                // 'salesTotalByPaymentMethod' => (array) json_decode(json_encode($dataNode->salesByPaymentMethod), true), // If structured XML
                // 'allDiscounts' => (float) ($dataNode->allDiscounts ?? 0),
                // 'otherReductionsAmount' => (float) ($dataNode->otherReductionsAmount ?? 0),
                // 'numberOfIncompleteSales' => (int) ($dataNode->numberOfIncompleteSales ?? 0),
            ];

            return [
                'message' => 'X Daily Report retrieved successfully.',
                'report' => $report,
                'kraResponse' => $response->body(),
            ];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }

    /**
     * Generates and retrieves a Z Daily Report from KRA.
     * Maps to KRA's Z daily report (Section 16.1, Doc 1, Page 12).
     * This command typically finalizes the day's operations on the device.
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @return array Parsed report data.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function generateZDailyReport(KraDevice $kraDevice): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        // KRA Z daily report command (check KRA spec for exact CMD string)
        $xmlPayload = KraApi::buildKraXml($pin, 'Z_REPORT'); // Assuming 'Z_REPORT' is the CMD

        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $endpointPath = '/api/generateZReport'; // Example: A common path for this command on KRA's API (check actual KRA docs)

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        try {
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload);
            $parsedXml = simplexml_load_string($response->body());

            $dataNode = $parsedXml->DATA ?? null;
            if (!$dataNode) {
                throw new \Exception("KRA Z Report response missing DATA node: " . $response->body());
            }

            // Parse the detailed report structure from DATA node
            // (Doc 1, Section 16.1, Page 12 outlines fields - similar to X report)
            $report = [
                'tradeName' => (string) ($dataNode->tradeName ?? null),
                'taxpayerPin' => (string) ($dataNode->PIN ?? null),
                'reportDate' => (string) ($dataNode->date ?? null),
                'reportTime' => (string) ($dataNode->time ?? null),
                'reportType' => (string) ($dataNode->reportType ?? 'Z_DAILY_REPORT'),
                'totalSalesAmountNS' => (float) ($dataNode->totalSalesAmountNS ?? 0.0),
                'numberOfSalesReceiptsNS' => (int) ($dataNode->numberOfSalesReceiptsNS ?? 0),
                'totalCreditNoteAmountNC' => (float) ($dataNode->totalCreditNoteAmountNC ?? 0.0),
                'numberOfCreditNotesNC' => (int) ($dataNode->numberOfCreditNotesNC ?? 0),
                // ... parse all 20 fields as per KRA Doc 1, Section 16.1
            ];

            return [
                'message' => 'Z Daily Report generated and retrieved successfully.',
                'report' => $report,
                'kraResponse' => $response->body(),
            ];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }

    /**
     * Retrieves a PLU (Price Look-Up Unit) Report from KRA.
     * Provides full details of each item, quantities sold, and amounts collected.
     * Maps to KRA's PLU report (Section 18.1, Doc 1, Page 13).
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @param string|null $startDate YYYY-MM-DD format (optional filter).
     * @param string|null $endDate YYYY-MM-DD format (optional filter).
     * @return array Parsed report data.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function getPLUReport(KraDevice $kraDevice, ?string $startDate = null, ?string $endDate = null): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        $dataPayload = [];
        if ($startDate) {
            $dataPayload['startDate'] = \DateTime::createFromFormat('Y-m-d', $startDate)->format('Ymd'); // KRA format YYYYMMDD
        }
        if ($endDate) {
            $dataPayload['endDate'] = \DateTime::createFromFormat('Y-m-d', $endDate)->format('Ymd'); // KRA format YYYYMMDD
        }

        // KRA PLU report command (check KRA spec for exact CMD string)
        $xmlPayload = KraApi::buildKraXml($pin, 'PLU_REPORT', $dataPayload); // Assuming 'PLU_REPORT' is the CMD

        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $endpointPath = '/api/getPLUReport'; // Example: A common path for this command on KRA's API (check actual KRA docs)

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        try {
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload);
            $parsedXml = simplexml_load_string($response->body());

            $dataNode = $parsedXml->DATA ?? null;
            if (!$dataNode) {
                throw new \Exception("KRA PLU Report response missing DATA node: " . $response->body());
            }

            // Parse the PLU report structure (Section 18.1, Doc 1, Page 13)
            $report = [
                'companyName' => (string) ($dataNode->companyName ?? null),
                'taxIdentificationNumber' => (string) ($dataNode->taxIdentificationNumber ?? null),
                'intervalDate' => (string) ($dataNode->intervalDate ?? null), // YYYYMMDD format, if available
                'intervalTime' => (string) ($dataNode->intervalTime ?? null), // HHMMSS format, if available
                'reportType' => (string) ($dataNode->reportType ?? 'PLU_REPORT'),
                'items' => [], // Array to hold item details
            ];

            // PLU report contains full details of each item.
            // Assuming it returns repeating <Item> or <Record> tags under <DATA>.
            if (isset($dataNode->items) && count($dataNode->items->children()) > 0) { // If items are nested
                foreach ($dataNode->items->children() as $itemNode) {
                    $item = [
                        'itemCode' => (string) ($itemNode->itemCode ?? null),
                        'itemName' => (string) ($itemNode->itemName ?? null),
                        'unitPrice' => (float) ($itemNode->unitPrice ?? 0.0),
                        'taxRate' => (float) ($itemNode->taxRate ?? 0.0),
                        'quantitySold' => (float) ($itemNode->quantitySold ?? 0.0),
                        'amountCollected' => (float) ($itemNode->amountCollected ?? 0.0),
                        'remainingQuantityInStock' => (float) ($itemNode->remainQuantityInStock ?? 0.0),
                    ];
                    $report['items'][] = $item;
                }
            } else if (count($dataNode->children()) > 0) {
                 // Alternative parsing if items are directly children of DATA and potentially repeat in flat structure
                 // This is more challenging with SimpleXMLElement. For single item example:
                 $singleItem = [
                        'itemCode' => (string) ($dataNode->itemCode ?? null),
                        'itemName' => (string) ($dataNode->itemName ?? null),
                        'unitPrice' => (float) ($dataNode->unitPrice ?? 0.0),
                        'taxRate' => (float) ($dataNode->taxRate ?? 0.0),
                        'quantitySold' => (float) ($dataNode->quantitySold ?? 0.0),
                        'amountCollected' => (float) ($dataNode->amountCollected ?? 0.0),
                        'remainingQuantityInStock' => (float) ($dataNode->remainQuantityInStock ?? 0.0),
                    ];
                if (!empty($singleItem['itemCode'])) { // Check if at least one field is non-null
                     $report['items'][] = $singleItem;
                }
            }

            return [
                'message' => 'PLU Report retrieved successfully.',
                'report' => $report,
                'kraResponse' => $response->body(),
            ];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }
} 