<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response as HttpResponse;
use SimpleXMLElement;
use App\Exceptions\KraApiException; // Import your custom exception

class KraApi
{
    protected string $baseUrl;
    protected int $strictTimeoutMs = 1000; // KRA mandated 1000ms timeout for direct OSCU/VSCU calls
    protected int $defaultTimeoutMs = 60000; // 60 seconds for general KRA API calls

    public function __construct()
    {
        $env = config('app.env');
        // Assuming KRA_API_SANDBOX_BASE_URL is defined in .env
        $this->baseUrl = ($env === 'production') ?
                        config('kra_api_production_base_url') :
                        config('kra_api_sandbox_base_url');
    }

    /**
     * Sends an XML command to a KRA endpoint.
     *
     * @param string $endpoint The KRA specific endpoint path (e.g., /selectInitOsdcInfo, /sendReceipt)
     * @param SimpleXMLElement $xmlPayload The XML payload to send. The root element should be <PIN>
     *                                      or contain the PIN within its structure if the endpoint requires it.
     * @param bool $isStrictTimeout If true, uses the 1000ms KRA-mandated timeout for OSCU/VSCU interactions.
     * @return \Illuminate\Http\Client\Response
     * @throws \App\Exceptions\KraApiException if KRA returns an error or communication fails.
     */
    public function sendCommand(string $endpoint, SimpleXMLElement $xmlPayload, bool $isStrictTimeout = false): HttpResponse
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $timeout = $isStrictTimeout ? $this->strictTimeoutMs : $this->defaultTimeoutMs;

        // Ensure the XML output is correct for transport
        $xmlString = $xmlPayload->asXML();

        logger()->info("Sending KRA command to {$url}", [
            'endpoint' => $endpoint,
            'timeout' => $timeout,
            'payload_start' => substr($xmlString, 0, 500) // Log only a portion
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
            ])
            ->timeout($timeout / 1000) // Guzzle timeout is in seconds
            ->retry(3, 100) // Retry 3 times, wait 100ms between retries for HTTP errors
            ->send('POST', $url, ['body' => $xmlString]);

            $responseBody = $response->body();
            logger()->info("Received KRA response from {$url}", [
                'status' => $response->status(),
                'response_start' => substr($responseBody, 0, 500) // Log only a portion
            ]);

            // Always throw HTTP exceptions (e.g., 4xx, 5xx) regardless of KRA's internal status
            $response->throw();

            // Attempt to parse XML response for KRA's internal status
            $parsedXml = simplexml_load_string($responseBody);
            if ($parsedXml === false) {
                throw new KraApiException(
                    "KRA response is not valid XML.",
                    $responseBody,
                    null, // No specific KRA error code if XML is malformed
                    $response->status()
                );
            }

            // Check KRA's internal STATUS code (P for pass, E for error)
            $kraInternalStatus = (string) $parsedXml->STATUS;
            if ($kraInternalStatus === 'E') {
                $kraErrorCode = (string) $parsedXml->DATA->ErrorCode ?? 'UNKNOWN_KRA_ERROR';
                throw new KraApiException(
                    "KRA reported an internal error.",
                    $responseBody,
                    $kraErrorCode,
                    $response->status()
                );
            }

            return $response;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            // This catches HTTP client errors (e.g., 404, 500 from KRA's HTTP server, network issues)
            logger()->error("KRA HTTP Request Error: " . $e->getMessage(), ['exception' => $e, 'response_body' => $e->response?->body()]);
            throw new KraApiException(
                "Failed to communicate with KRA API server or received HTTP error.",
                $e->response?->body(),
                null, // No specific KRA error code from HTTP client error
                $e->response?->status() ?? $e->getCode(),
                $e
            );
        } catch (KraApiException $e) {
            // Re-throw our custom exception if it was already generated (e.g., from XML parsing or 'E' status)
            throw $e;
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions during the process
            logger()->error("Unexpected KRA API communication error: " . $e->getMessage(), ['exception' => $e]);
            throw new KraApiException(
                "An unexpected error occurred during KRA API interaction.",
                $responseBody ?? null,
                null,
                $response->status() ?? null,
                $e->getCode(),
                $e
            );
        }
    }
}