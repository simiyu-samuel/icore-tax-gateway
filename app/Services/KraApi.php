<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response as HttpResponse;
use SimpleXMLElement;
use App\Exceptions\KraApiException; // Import your custom exception
use Throwable;        // Import this

class KraApi
{
    protected string $baseUrl;
    protected int $strictTimeoutMs = 1000; // KRA mandated 1000ms timeout for direct OSCU/VSCU calls
    protected int $generalTimeoutMs = 60000; // 60 seconds for general KRA API calls

    public function __construct()
    {
        $env = config('app.env');
        // Use the new KRA configuration file for base URLs
        $this->baseUrl = ($env === 'production')
            ? config('kra.api_production_base_url')
            : config('kra.api_sandbox_base_url');
        
        // Set timeout values from config
        $this->strictTimeoutMs = config('kra_api.strict_timeout_ms', 1000);
        $this->generalTimeoutMs = config('kra_api.default_timeout_ms', 60000);
    }

    /**
     * Helper method to build KRA XML payload.
     * Creates top-level <PIN>, <CMD>, and <DATA> nodes.
     * Handles simple key-value pairs within <DATA>.
     *
     * @param string $pin The KRA PIN for the request.
     * @param string $command The KRA command string (e.g., 'SEND_RECEIPT', 'STATUS').
     * @param array $data An associative array for the <DATA> node content.
     * @return SimpleXMLElement
     */
    public static function buildKraXml(string $pin, string $command, array $data = []): SimpleXMLElement
    {
        // KRA's structure is typically flat at the top level: PIN, CMD, DATA
        // SimpleXMLElement needs a single root. We'll use a dummy root for building,
        // then extract the children for the actual payload.
        $tempRoot = new SimpleXMLElement('<root/>');

        $tempRoot->addChild('PIN', htmlspecialchars($pin));
        $tempRoot->addChild('CMD', htmlspecialchars($command));
        $dataElement = $tempRoot->addChild('DATA');

        foreach ($data as $key => $value) {
            $dataElement->addChild($key, htmlspecialchars((string) $value));
        }

        return $tempRoot; // Return the temporary root, sendCommand will process it
    }

    /**
     * Sends an XML command to a KRA endpoint.
     *
     * @param string $endpoint The KRA specific endpoint path (e.g., '/selectInitOsdcInfo', or empty for root commands like 'STATUS').
     * @param SimpleXMLElement $xmlPayload The XML payload to send.
     * @param bool $isStrictTimeout If true, uses the KRA-mandated 1000ms timeout.
     * @return \Illuminate\Http\Client\Response
     * @throws \App\Exceptions\KraApiException if KRA returns an error or communication fails.
     */
    public function sendCommand(string $endpoint, SimpleXMLElement $xmlPayload, bool $isStrictTimeout = false): HttpResponse
    {
        // Check if simulation mode is enabled
        if (config('kra.simulation_mode', false)) {
            return $this->getSimulatedResponse($endpoint, $xmlPayload);
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $timeoutMs = $isStrictTimeout ? $this->strictTimeoutMs : $this->generalTimeoutMs;

        $kraPayloadElements = '';
        foreach ($xmlPayload->children() as $child) {
            $kraPayloadElements .= $child->asXML();
        }
        $xmlString = $kraPayloadElements;

        logger()->info("KRA_REQUEST: Sending command to {$url}", [
            'payload' => $xmlString,
            'timeout_ms' => $timeoutMs,
            'is_strict_timeout' => $isStrictTimeout,
            'trace_id' => request()->attributes->get('traceId')
        ]);

        $responseBody = null; // Always define this before try

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ])
            ->timeout($timeoutMs / 1000)
            ->send('POST', $url, ['body' => $xmlString]);

            $responseBody = $response->body();

            logger()->info("KRA_RESPONSE: Received response from {$url}", [
                'status' => $response->status(),
                'body' => $responseBody,
                'trace_id' => request()->attributes->get('traceId')
            ]);

            $response->throw();

            $parsedXml = @simplexml_load_string($responseBody);
            if ($parsedXml === false) {
                if (empty($responseBody)) {
                    throw new \Exception("KRA response body is empty or unparseable XML.");
                }
                throw new \Exception("KRA response is not valid XML: " . $responseBody);
            }

            $statusCode = (string) ($parsedXml->STATUS ?? 'UNKNOWN');
            if ($statusCode === 'E') {
                $kraErrorCode = (string) ($parsedXml->DATA->ErrorCode ?? 'UNKNOWN_KRA_ERROR');
                throw new KraApiException(
                    "KRA returned an error status: [{$kraErrorCode}] " . ($parsedXml->DATA->message ?? 'No specific message.'),
                    $responseBody,
                    $kraErrorCode
                );
            }

            return $response;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            logger()->error("KRA_HTTP_ERROR: " . $e->getMessage(), ['exception' => $e, 'trace_id' => request()->attributes->get('traceId')]);
            throw new KraApiException(
                "Failed to communicate with KRA API due to HTTP error: " . $e->getMessage(),
                $e->response ? $e->response->body() : null,
                null,
                $e->response ? $e->response->status() : 0,
                0,
                $e
            );
        } catch (KraApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error("KRA_GENERAL_ERROR: " . $e->getMessage(), ['exception' => $e, 'trace_id' => request()->attributes->get('traceId')]);
            throw new KraApiException(
                "An unexpected error occurred during KRA API communication: " . $e->getMessage(),
                $responseBody,
                null,
                0,
                0,
                $e
            );
        }
    }

    /**
     * Get simulated response for development/testing.
     */
    private function getSimulatedResponse(string $endpoint, SimpleXMLElement $xmlPayload): HttpResponse
    {
        logger()->info("KRA_SIMULATION: Simulating response for endpoint {$endpoint}", [
            'payload' => $xmlPayload->asXML(),
            'trace_id' => request()->attributes->get('traceId')
        ]);

        // Parse the XML payload to determine the command
        $command = (string) ($xmlPayload->CMD ?? 'UNKNOWN');
        
        // Generate appropriate mock response based on command
        $mockResponse = $this->generateMockResponse($command, $xmlPayload);
        
        // Create a mock HTTP response
        return new \Illuminate\Http\Client\Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $mockResponse)
        );
    }

    /**
     * Generate mock response based on KRA command.
     */
    private function generateMockResponse(string $command, SimpleXMLElement $xmlPayload): string
    {
        switch ($command) {
            case 'selectInitOsdcInfo':
                return $this->getMockInitResponse();
            case 'SEND_ITEM':
                return $this->getMockSendItemResponse();
            case 'RECV_ITEM':
                return $this->getMockRecvItemResponse();
            case 'SEND_PURCHASE':
                return $this->getMockSendPurchaseResponse();
            case 'RECV_PURCHASE':
                return $this->getMockRecvPurchaseResponse();
            case 'SEND_INVENTORY':
                return $this->getMockSendInventoryResponse();
            case 'RECV_INVENTORY':
                return $this->getMockRecvInventoryResponse();
            default:
                return $this->getMockGenericResponse($command);
        }
    }

    private function getMockInitResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <branchOfficeId>00</branchOfficeId>
        <deviceType>VSCU</deviceType>
        <deviceStatus>ACTIVE</deviceStatus>
        <lastSyncTime>20250704120000</lastSyncTime>
        <SCU_ID>KRACU000000001</SCU_ID>
    </DATA>
</root>';
    }

    private function getMockSendItemResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <message>Item registered successfully</message>
        <itemCode>ITEM001</itemCode>
        <registrationDate>20250704120000</registrationDate>
    </DATA>
</root>';
    }

    private function getMockRecvItemResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <itemCd>ITEM001</itemCd>
        <itemClsCd>CLS001</itemClsCd>
        <itemNm>Sample Item</itemNm>
        <itemTyCd>2</itemTyCd>
        <taxTyCd>B</taxTyCd>
        <InitlWhUntpc>100.00</InitlWhUntpc>
        <dfltDlUntpc>120.00</dfltDlUntpc>
        <useYn>Y</useYn>
    </DATA>
</root>';
    }

    private function getMockSendPurchaseResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <message>Purchase data sent successfully</message>
        <invoiceId>INV' . date('YmdHis') . '</invoiceId>
        <registrationDate>' . now()->format('YmdHis') . '</registrationDate>
        <status>SUCCESS</status>
    </DATA>
</root>';
    }

    private function getMockRecvPurchaseResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <purchases>
            <purchase>
                <invoiceId>INV20250704120000</invoiceId>
                <invoiceDate>20250704</invoiceDate>
                <customerName>John Doe</customerName>
                <customerPin>A123456789B</customerPin>
                <totalAmount>1500.00</totalAmount>
                <taxAmount>225.00</taxAmount>
                <items>
                    <item>
                        <itemCode>ITEM001</itemCode>
                        <itemName>Sample Product</itemName>
                        <quantity>2</quantity>
                        <unitPrice>750.00</unitPrice>
                        <totalPrice>1500.00</totalPrice>
                        <taxRate>15.00</taxRate>
                        <taxAmount>225.00</taxAmount>
                    </item>
                </items>
            </purchase>
        </purchases>
    </DATA>
</root>';
    }

    private function getMockSendInventoryResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <message>Inventory movement data sent successfully</message>
        <itemCode>ITEM001</itemCode>
        <branchId>00</branchId>
        <quantity>100</quantity>
        <updateDate>' . now()->format('YmdHis') . '</updateDate>
        <status>SUCCESS</status>
    </DATA>
</root>';
    }

    private function getMockRecvInventoryResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <inventory>
            <item>
                <bhfId>00</bhfId>
                <itemClsCd>3026530000</itemClsCd>
                <itemCd>ITEM001</itemCd>
                <itemNm>Sample Product</itemNm>
                <qty>150</qty>
                <updDt>20250705120000</updDt>
                <lastSyncTime>20250705120000</lastSyncTime>
            </item>
            <item>
                <bhfId>01</bhfId>
                <itemClsCd>3026530001</itemClsCd>
                <itemCd>ITEM002</itemCd>
                <itemNm>Another Product</itemNm>
                <qty>75</qty>
                <updDt>20250705120000</updDt>
                <lastSyncTime>20250705120000</lastSyncTime>
            </item>
        </inventory>
    </DATA>
</root>';
    }

    private function getMockGenericResponse(string $command): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<root>
    <STATUS>P</STATUS>
    <DATA>
        <message>Command executed successfully</message>
        <command>' . htmlspecialchars($command) . '</command>
        <timestamp>' . now()->format('YmdHis') . '</timestamp>
    </DATA>
</root>';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $url): void
    {
        $this->baseUrl = $url;
    }
}