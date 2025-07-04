<?php
namespace App\Services;

use App\Models\KraDevice;
use App\Exceptions\KraApiException;
use Illuminate\Support\Str;

class KraItemService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Register or update an item with KRA (SEND_ITEM).
     */
    public function registerItem(KraDevice $kraDevice, array $itemData): array
    {
        $kraItemPayload = [
            'itemCd' => $itemData['itemCode'],
            'itemClsCd' => $itemData['itemClassificationCode'],
            'itemNm' => $itemData['itemName'],
            'itemTyCd' => $itemData['itemTypeCode'] ?? '2',
            'itemStd' => $itemData['itemStandard'] ?? '',
            'OrgplceCd' => $itemData['originCountryCode'] ?? 'KE',
            'PkgUnitCd' => $itemData['packagingUnitCode'],
            'QtyUnitCd' => $itemData['quantityUnitCode'],
            'AdiInfo' => $itemData['additionalInfo'] ?? '0001',
            'InitlWhUntpc' => $itemData['initialWholesaleUnitPrice'],
            'InitlQty' => $itemData['initialQuantity'],
            'AvgWhUntpc' => $itemData['averageWholesaleUnitPrice'] ?? $itemData['initialWholesaleUnitPrice'],
            'dfltDlUntpc' => $itemData['defaultSellingUnitPrice'],
            'taxTyCd' => $itemData['taxType'],
            'rm' => $itemData['remark'] ?? '[FREE TEXT]',
            'useYn' => $itemData['inUse'] ? 'Y' : 'N',
            'regusrId' => $itemData['registerUserId'] ?? 'GatewaySystem',
            'regDt' => $itemData['registerDate'] ?? now()->format('YmdHis'),
            'updusrId' => $itemData['updateUserId'] ?? 'GatewaySystem',
            'updDt' => $itemData['updateDate'] ?? now()->format('YmdHis'),
            'safetyQty' => $itemData['safetyQuantity'] ?? 0,
            'useBarcode' => $itemData['useBarcode'] ? 'Y' : 'N',
            'changeYn' => $itemData['changeAllowed'] ? 'Y' : 'N',
            'useAdiYn' => $itemData['useAdditionalInfoAllowed'] ? 'Y' : 'N',
        ];

        $xmlPayload = KraApi::buildKraXml(
            $kraDevice->taxpayerPin->pin,
            'SEND_ITEM',
            $kraItemPayload
        );

        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
            ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
            config('kra.api_sandbox_base_url');
        $endpointPath = '/api/sendItem';
        $originalBaseUrl = $this->kraApi->baseUrl;
        $this->kraApi->baseUrl = $targetBaseUrl;

        try {
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload, false);
            $parsedXml = simplexml_load_string($response->body());
            $status = (string) ($parsedXml->STATUS ?? 'UNKNOWN');
            return [
                'status' => $status,
                'message' => 'Item registration command sent to KRA.',
                'kraResponse' => $response->body()
            ];
        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->baseUrl = $originalBaseUrl;
        }
    }

    /**
     * Get item list from KRA (RECV_ITEM).
     */
    public function getItems(KraDevice $kraDevice): array
    {
        $xmlPayload = KraApi::buildKraXml(
            $kraDevice->taxpayerPin->pin,
            'RECV_ITEM'
        );
        $targetBaseUrl = ($kraDevice->device_type === 'VSCU') ?
            ($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url')) :
            config('kra.api_sandbox_base_url');
        $endpointPath = '/api/recvItem';
        $originalBaseUrl = $this->kraApi->baseUrl;
        $this->kraApi->baseUrl = $targetBaseUrl;
        try {
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload, false);
            $parsedXml = simplexml_load_string($response->body());
            $items = [];
            if (isset($parsedXml->DATA)) {
                foreach ($parsedXml->DATA->children() as $child) {
                    if (Str::endsWith($child->getName(), 'Record') || $child->getName() === 'Item') {
                        $item = [];
                        foreach ($child->children() as $field) {
                            $item[$field->getName()] = (string) $field;
                        }
                        $items[] = $item;
                    } else {
                        $items[] = (array) $parsedXml->DATA;
                        break;
                    }
                }
            }
            if (empty($items) && isset($parsedXml->DATA) && count((array) $parsedXml->DATA) > 0) {
                $singleItem = [];
                foreach ($parsedXml->DATA->children() as $field) {
                    $singleItem[$field->getName()] = (string) $field;
                }
                if (!empty($singleItem)) {
                    $items[] = $singleItem;
                }
            }
            return [
                'message' => 'Item list retrieved successfully.',
                'items' => $items,
                'kraResponse' => $response->body()
            ];
        } catch (KraApiException $e) {
            throw $e;
        } finally {
            $this->kraApi->baseUrl = $originalBaseUrl;
        }
    }
} 