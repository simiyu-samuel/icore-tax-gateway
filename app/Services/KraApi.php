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
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $timeoutMs = $isStrictTimeout ? $this->strictTimeoutMs : $this->generalTimeoutMs;

        // --- CRITICAL: Flatten the XML to remove the dummy root and send KRA's expected structure ---
        $kraPayloadElements = '';
        foreach ($xmlPayload->children() as $child) {
            $kraPayloadElements .= $child->asXML();
        }
        $xmlString = $kraPayloadElements;
        // --- END CRITICAL ---

        // Log the outgoing request
        logger()->info("KRA_REQUEST: Sending command to {$url}", [
            'payload' => $xmlString,
            'timeout_ms' => $timeoutMs,
            'is_strict_timeout' => $isStrictTimeout,
            'trace_id' => request()->attributes->get('traceId')
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ])
            ->timeout($timeoutMs / 1000) // Guzzle timeout is in seconds
            ->send('POST', $url, ['body' => $xmlString]);

            $responseBody = $response->body();

            // Log the incoming response
            logger()->info("KRA_RESPONSE: Received response from {$url}", [
                'status' => $response->status(),
                'body' => $responseBody,
                'trace_id' => request()->attributes->get('traceId')
            ]);

            // Throws Guzzle exceptions for 4xx/5xx HTTP codes
            $response->throw();

            // Attempt to parse XML response for KRA's internal status (P/E)
            $parsedXml = @simplexml_load_string($responseBody); // Use @ to suppress XML errors, handle them below
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
                $e
            );
        } catch (KraApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            logger()->error("KRA_GENERAL_ERROR: " . $e->getMessage(), ['exception' => $e, 'trace_id' => request()->attributes->get('traceId')]);
            throw new KraApiException(
                "An unexpected error occurred during KRA API communication: " . $e->getMessage(),
                isset($responseBody) ? $responseBody : null,
                null,
                0,
                $e
            );
        }
    }
}