<?php
namespace App\Services;

use App\Models\KraDevice;
use App\Exceptions\KraApiException;
use SimpleXMLElement;
use Illuminate\Support\Arr;

class KraPurchaseService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Sends purchase data to KRA. This typically involves:
     * 1. CMD: SEND_PURCHASE (for the purchase header details)
     * 2. CMD: SEND_PURCHASEITEM (for each item in the purchase)
     *
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @param array $purchaseData The purchase data received from the API request.
     * @return array Containing status and message of the operation.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function sendPurchase(KraDevice $kraDevice, array $purchaseData): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        // Determine the base URL for KRA API calls (direct or via VSCU JAR)
        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        try {
            // --- Part 1: Send CMD: SEND_PURCHASE (Purchase Header) ---
            // Mapping from Doc 1, Section 24.8.4, Page 22
            $purchaseHeaderPayload = [
                'InvId' => $purchaseData['invoiceId'],
                'bhfId' => $purchaseData['branchId'],
                'bencId' => $purchaseData['supplierPin'],
                'bcncNm' => $purchaseData['supplierName'],
                'bencSdcId' => $purchaseData['supplierCuId'],
                'regTyCd' => $purchaseData['registrationTypeCode'],
                'refId' => $purchaseData['referenceId'],
                'payTyCd' => $purchaseData['paymentTypeCode'],
                'invStatusCd' => $purchaseData['invoiceStatusCode'],
                'ocde' => $purchaseData['transactionDate'], // YYYYMMDD
                'validDt' => $purchaseData['validDate'] ?? $purchaseData['transactionDate'], // YYYYMMDD
                'cancelReqDt' => $purchaseData['cancelRequestDate'] ?? null,
                'cancelDt' => $purchaseData['cancelDate'] ?? null,
                'refundDt' => $purchaseData['refundDate'] ?? null,
                'cancelTyCd' => $purchaseData['cancelTypeCode'] ?? null,
                'totNumItem' => count($purchaseData['items']),
                'totTaxablAmtA' => $purchaseData['totalTaxableAmountA'] ?? 0,
                'totTaxablAmtB' => $purchaseData['totalTaxableAmountB'] ?? 0,
                'totTaxablAmtC' => $purchaseData['totalTaxableAmountC'] ?? 0,
                'totTaxablAmtD' => $purchaseData['totalTaxableAmountD'] ?? 0,
                'totTaxablAmtE' => $purchaseData['totalTaxableAmountE'] ?? 0,
                'totTaxA' => $purchaseData['totalTaxA'] ?? 0,
                'totTaxB' => $purchaseData['totalTaxB'] ?? 0,
                'totTaxC' => $purchaseData['totalTaxC'] ?? 0,
                'totTaxD' => $purchaseData['totalTaxD'] ?? 0,
                'totTaxE' => $purchaseData['totalTaxE'] ?? 0,
                'totSplpc' => $purchaseData['totalSupplierPrice'], // Total Supplier Amount
                'totTax' => $purchaseData['totalTax'], // Total Vat amount
                'totAmt' => $purchaseData['totalAmount'], // Total Amount
                'remark' => $purchaseData['remark'] ?? '[FREE TEXT]',
                'regusrId' => $purchaseData['registerUserId'],
                'regDt' => $purchaseData['registerDate'], // YYYYMMDDHHMMSS
            ];

            $purchaseHeaderXml = KraApi::buildKraXml($pin, 'SEND_PURCHASE', $purchaseHeaderPayload);
            $this->kraApi->sendCommand('/api/sendPurchase', $purchaseHeaderXml); // Example endpoint

            // --- Part 2: Send CMD: SEND_PURCHASEITEM (Individual Items) ---
            // Mapping from Doc 1, Section 24.8.6, Page 24
            foreach ($purchaseData['items'] as $item) {
                $purchaseItemPayload = [
                    'invId' => $purchaseData['invoiceId'], // Link to the header invoice ID
                    'bhfId' => $purchaseData['branchId'],
                    'itemSeq' => $item['sequence'],
                    'itemClsCd' => $item['itemClassificationCode'],
                    'itemCd' => $item['itemCode'],
                    'itemNm' => $item['itemName'],
                    'bcncItemClsCd' => $item['supplierItemClassificationCode'],
                    'bcncItemCd' => $item['supplierItemCode'],
                    'bcncItemNm' => $item['supplierItemName'],
                    'pkgUnitCd' => $item['packagingUnitCode'],
                    'pkgQty' => $item['packagingQuantity'],
                    'qtyUnitCd' => $item['quantityUnitCode'],
                    'qty' => $item['quantity'],
                    'expirDt' => $item['expiryDate'] ?? null, // YYYYMMDD
                    'untpc' => $item['unitPrice'],
                    'splpc' => $item['supplierPrice'],
                    'dcRate' => $item['discountRate'],
                    'dcAmt' => $item['discountAmount'],
                    'taxablAmt' => $item['taxableAmount'],
                    'taxTyCd' => $item['taxType'],
                    'tax' => $item['taxAmount'],
                ];
                $purchaseItemXml = KraApi::buildKraXml($pin, 'SEND_PURCHASEITEM', $purchaseItemPayload);
                $this->kraApi->sendCommand('/api/sendPurchaseItem', $purchaseItemXml); // Example endpoint
            }

            return ['message' => 'Purchase data sent to KRA successfully.'];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }

    /**
     * Retrieves purchase data from KRA. This typically involves:
     * 1. CMD: RECV_PURCHASE (for purchase header details)
     * 2. CMD: RECV_PURCHASEITEM (for individual items associated with purchases)
     *
     * The KRA documentation implies these are separate calls.
     * Parsing multiple records from a flat XML structure (like in Doc 1, Page 23 for RECV_PURCHASEITEM)
     * requires more sophisticated parsing than `SimpleXMLElement` naturally provides for repeating flat groups.
     * For now, this will return an example of one item if found. A more advanced parser would be needed for many.
     *
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @param array $filter (e.g., date range, invoice ID). Not explicit in KRA RECV commands for DATA node.
     * @return array
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function getPurchases(KraDevice $kraDevice, array $filter = []): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        $purchases = [];
        $purchaseItems = [];

        try {
            // --- Part 1: Retrieve CMD: RECV_PURCHASE (Purchase Headers) ---
            $purchaseRecvXml = KraApi::buildKraXml($pin, 'RECV_PURCHASE');
            $response = $this->kraApi->sendCommand('/api/recvPurchase', $purchaseRecvXml); // Example endpoint
            $parsedPurchaseXml = simplexml_load_string($response->body());

            if (isset($parsedPurchaseXml->DATA)) {
                // KRA's RECV_PURCHASE (Doc 1, Page 21) shows single record example.
                // If multiple records, they are likely wrapped in a <Record> or similar.
                // If they are flat, repeating fields, this loop won't work correctly for multiple.
                foreach ($parsedPurchaseXml->DATA->children() as $child) {
                    // Assuming records are wrapped in a distinct tag like <Record> or similar
                    if ($child->getName() === 'Record' || $child->getName() === 'Purchase') { // Adjust tag name
                        $purchase = [];
                        foreach ($child->children() as $field) {
                            $purchase[$field->getName()] = (string) $field;
                        }
                        $purchases[] = $purchase;
                    } elseif (!empty((array) $parsedPurchaseXml->DATA)) {
                        // If DATA contains fields directly (single purchase)
                        $singlePurchase = [];
                        foreach ($parsedPurchaseXml->DATA->children() as $field) {
                            $singlePurchase[$field->getName()] = (string) $field;
                        }
                        if (!empty($singlePurchase)) {
                            $purchases[] = $singlePurchase;
                        }
                        break; // Assume only one purchase if not structured as repeating records
                    }
                }
            }

            // --- Part 2: Retrieve CMD: RECV_PURCHASEITEM (Purchase Items) ---
            $purchaseItemsRecvXml = KraApi::buildKraXml($pin, 'RECV_PURCHASEITEM');
            $responseItems = $this->kraApi->sendCommand('/api/recvPurchaseItem', $purchaseItemsRecvXml); // Example endpoint
            $parsedPurchaseItemsXml = simplexml_load_string($responseItems->body());

            if (isset($parsedPurchaseItemsXml->DATA)) {
                // KRA's RECV_PURCHASEITEM (Doc 1, Page 23) also shows a single record example.
                // If multiple items, structure is key. Assuming flat repeating means complex parsing.
                foreach ($parsedPurchaseItemsXml->DATA->children() as $child) {
                     // Assuming records are wrapped in a distinct tag like <ItemRecord> or similar
                    if ($child->getName() === 'Record' || $child->getName() === 'PurchaseItem') { // Adjust tag name
                        $item = [];
                        foreach ($child->children() as $field) {
                            $item[$field->getName()] = (string) $field;
                        }
                        $purchaseItems[] = $item;
                    } elseif (!empty((array) $parsedPurchaseItemsXml->DATA)) {
                        // If DATA contains fields directly (single item)
                        $singleItem = [];
                        foreach ($parsedPurchaseItemsXml->DATA->children() as $field) {
                            $singleItem[$field->getName()] = (string) $field;
                        }
                        if (!empty($singleItem)) {
                            $purchaseItems[] = $singleItem;
                        }
                        break; // Assume only one item if not structured as repeating records
                    }
                }
            }

            return [
                'message' => 'Purchase data retrieved successfully.',
                'purchases' => $purchases,
                'purchaseItems' => $purchaseItems,
                'kraResponsePurchases' => $response->body(),
                'kraResponsePurchaseItems' => $responseItems->body(),
            ];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }
} 