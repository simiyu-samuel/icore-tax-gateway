<?php
namespace App\Services;

use App\Models\KraDevice;
use App\Exceptions\KraApiException;
use SimpleXMLElement;
use Illuminate\Support\Arr;

class KraInventoryService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Sends inventory movement data (stock in/out) to KRA.
     * Maps to CMD: SEND_INVENTORY (Section 24.8.7, Doc 1, Page 24).
     *
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @param array $inventoryData The inventory movement data received from the API request.
     * @return array Containing status and message of the operation.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function sendInventoryMovement(KraDevice $kraDevice, array $inventoryData): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        // Determine the base URL for KRA API calls (direct or via VSCU JAR)
        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        try {
            // Mapping from Doc 1, Section 24.8.7.1, Page 24
            $inventoryPayload = [
                'bhfId' => $inventoryData['branchId'],
                'itemClsCd' => $inventoryData['itemClassificationCode'],
                'itemCd' => $inventoryData['itemCode'],
                'qty' => $inventoryData['quantity'],
                'updDt' => $inventoryData['updateDate'], // YYYYMMDDHHMMSS format
            ];

            $xmlPayload = KraApi::buildKraXml($pin, 'SEND_INVENTORY', $inventoryPayload);

            $endpointPath = '/api/sendInventory'; // Example: A common path for this command on KRA's API (check actual KRA docs)

            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload, false); // Not strict timeout

            $parsedXml = simplexml_load_string($response->body());
            $status = (string) ($parsedXml->STATUS ?? 'UNKNOWN');

            return [
                'status' => $status,
                'message' => 'Inventory movement data sent to KRA.',
                'kraResponse' => $response->body() // For audit/debugging
            ];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }

    /**
     * Retrieves inventory data from KRA.
     * Maps to CMD: RECV_INVENTORY (Section 24.8.8, Doc 1, Page 25).
     *
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @param array $filterData Optional filter data for inventory retrieval.
     * @return array Containing inventory data from KRA.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function getInventoryData(KraDevice $kraDevice, array $filterData = []): array
    {
        $pin = $kraDevice->taxpayerPin->pin;

        // Determine the base URL for KRA API calls
        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
                         ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
                         config('kra.api_sandbox_base_url');

        $originalBaseUrl = $this->kraApi->getBaseUrl();
        $this->kraApi->setBaseUrl($targetBaseUrl);

        try {
            // Build filter payload if provided
            $inventoryPayload = [];
            if (!empty($filterData)) {
                if (isset($filterData['branchId'])) {
                    $inventoryPayload['bhfId'] = $filterData['branchId'];
                }
                if (isset($filterData['itemCode'])) {
                    $inventoryPayload['itemCd'] = $filterData['itemCode'];
                }
                if (isset($filterData['startDate'])) {
                    $inventoryPayload['startDt'] = $filterData['startDate'];
                }
                if (isset($filterData['endDate'])) {
                    $inventoryPayload['endDt'] = $filterData['endDate'];
                }
            }

            $xmlPayload = KraApi::buildKraXml($pin, 'RECV_INVENTORY', $inventoryPayload);

            $endpointPath = '/api/recvInventory'; // Example endpoint path

            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload, false);

            $parsedXml = simplexml_load_string($response->body());
            $status = (string) ($parsedXml->STATUS ?? 'UNKNOWN');

            if ($status === 'P') {
                // Parse inventory data from response
                $inventoryData = [];
                if (isset($parsedXml->DATA->inventory)) {
                    foreach ($parsedXml->DATA->inventory as $item) {
                        $inventoryData[] = [
                            'branchId' => (string) ($item->bhfId ?? ''),
                            'itemClassificationCode' => (string) ($item->itemClsCd ?? ''),
                            'itemCode' => (string) ($item->itemCd ?? ''),
                            'itemName' => (string) ($item->itemNm ?? ''),
                            'quantity' => (float) ($item->qty ?? 0),
                            'updateDate' => (string) ($item->updDt ?? ''),
                            'lastSyncTime' => (string) ($item->lastSyncTime ?? ''),
                        ];
                    }
                }

                return [
                    'status' => $status,
                    'message' => 'Inventory data retrieved successfully.',
                    'inventory' => $inventoryData,
                    'kraResponse' => $response->body()
                ];
            }

            return [
                'status' => $status,
                'message' => 'Failed to retrieve inventory data.',
                'inventory' => [],
                'kraResponse' => $response->body()
            ];

        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
    }
} 