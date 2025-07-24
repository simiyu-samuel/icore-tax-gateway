<?php
namespace App\Services;

use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use Illuminate\Support\Str;
use SimpleXMLElement;
use App\Exceptions\KraApiException;

class KraDeviceService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Initializes/activates a KRA device (OSCU or VSCU).
     * This maps to the /selectInitOsdcInfo endpoint in KRA spec.
     * @param array $data Contains taxpayerPin, branchOfficeId, deviceType, deviceSerialNumber.
     * @return KraDevice
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception If KRA response is unparseable or unexpected.
     */
    public function initializeDevice(array $data): KraDevice
    {
        $taxpayerPin = TaxpayerPin::where('pin', $data['taxpayerPin'])->firstOrFail();

        // Prepare XML payload for /selectInitOsdcInfo
        // KRA spec: (url: /selectInitOsdcInfo) - needs PIN, branch office ID, equipment information
        $xmlPayload = KraApi::buildKraXml(
            $taxpayerPin->pin,
            'selectInitOsdcInfo',
            [
                'branchOfficeId' => $data['branchOfficeId'],
                'deviceType' => $data['deviceType'],
                'deviceSerialNumber' => $data['deviceSerialNumber'], // Pass serial number for OSCU
            ]
        );

        // Determine the actual endpoint for the KRA API call.
        // For OSCU, it's typically the KRA API base URL + specific path.
        // For VSCU, it's the local JAR URL + specific path.
        $endpointPath = '/selectInitOsdcInfo'; // This is the URL path KRA document suggests

        // Temporarily set the base URL for the KraApi instance if it's a VSCU to hit the local JAR
        $originalBaseUrl = $this->kraApi->getBaseUrl();
        if ($data['deviceType'] === 'VSCU') {
            $this->kraApi->setBaseUrl(config('kra.vscu_jar_base_url'));
        }

        try {
            // Send command with strict timeout for initialization
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload, true); // `true` for strict timeout

            // Check Content-Type to determine how to parse
            $contentType = $response->header('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $parsed = json_decode($response->body(), true);
                $kraScuId = $parsed['DATA']['SCU_ID'] ?? null;
            } else {
                $parsedXml = simplexml_load_string($response->body());
                $kraScuId = (string) ($parsedXml->DATA->SCU_ID ?? null); // Adjust path based on actual KRA response
            }

            if (empty($kraScuId)) {
                throw new \Exception("KRA initialization response missing SCU_ID: " . $response->body());
            }

            // Check if device already exists in our local database
            $kraDevice = KraDevice::where('kra_scu_id', $kraScuId)
                                  ->where('taxpayer_pin_id', $taxpayerPin->id)
                                  ->first();

            // Handle KRA Error Code 41 (already activated) explicitly if not caught by KraApi.php
            // Our KraApi.php handles it. If this code path is reached, it means KRA successfully returned SCU_ID.
            // If KRA says '41' but gives SCU ID, we should update our local record to 'ACTIVATED'.

            if (!$kraDevice) {
                $kraDevice = new KraDevice();
                $kraDevice->id = (string) Str::uuid(); // Generate new UUID for Gateway's device ID
            }

            $kraDevice->taxpayer_pin_id = $taxpayerPin->id;
            $kraDevice->kra_scu_id = $kraScuId;
            $kraDevice->device_type = $data['deviceType'];
            $kraDevice->status = 'ACTIVATED'; // Device is now successfully activated or re-confirmed
            $kraDevice->config = array_merge($kraDevice->config ?? [], [ // Merge with existing config
                'branch_office_id' => $data['branchOfficeId'],
                'initial_activation_raw_response' => $response->body(), // Store raw response for audit
                'vscu_jar_url_used' => ($data['deviceType'] === 'VSCU') ? $this->kraApi->getBaseUrl() : null,
            ]);
            $kraDevice->save();

            return $kraDevice;

        } catch (KraApiException $e) {
            // If KRA returns error code 41 (already activated) for the initialize command
            if ($e->kraErrorCode === '41') {
                // Try to extract the SCU ID from the error response to find the existing device
                $existingKraScuId = $this->extractKraScuIdFromErrorResponse($e->kraRawResponse);
                if ($existingKraScuId) {
                    $kraDevice = KraDevice::where('kra_scu_id', $existingKraScuId)
                                          ->where('taxpayer_pin_id', $taxpayerPin->id)
                                          ->first();
                    if ($kraDevice) {
                        $kraDevice->update(['status' => 'ACTIVATED']); // Confirm it's activated
                        logger()->info("KRA device {$existingKraScuId} for PIN {$taxpayerPin->pin} was already activated. Returning existing record.", ['trace_id' => request()->attributes->get('traceId')]);
                        return $kraDevice; // Return the existing device instead of erroring
                    }
                }
            }
            // If not an 'already activated' scenario, or if we couldn't find the existing device, re-throw
            throw $e;
        } finally {
            // Ensure baseUrl is restored even if an error occurs
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
        throw new \Exception('initializeDevice: Unexpected error, no device returned.');
    }

    /**
     * Get the status of a KRA device.
     * Maps to CMD: STATUS (Section 21.7.8, Doc 1).
     * @param KraDevice $kraDevice The local KraDevice model instance.
     * @return array Contains parsed status data.
     * @throws \App\Exceptions\KraApiException
     * @throws \Exception
     */
    public function getDeviceStatus(KraDevice $kraDevice): array
    {
        // Build XML payload for CMD:STATUS. KRA CMD:STATUS typically doesn't need DATA.
        $xmlPayload = KraApi::buildKraXml($kraDevice->taxpayerPin->pin, 'STATUS');

        // The endpoint for STATUS command is usually just the base URL (empty string for sendCommand).
        // KRA's spec shows it as a command, not a path.
        $endpointPath = ''; // Or a specific path if KRA defines one for STATUS (e.g. '/api/status')

        // Temporarily set the base URL for the KraApi instance if it's a VSCU to hit the local JAR
        $originalBaseUrl = $this->kraApi->getBaseUrl();
        if ($kraDevice->device_type === 'VSCU') {
            $this->kraApi->setBaseUrl($kraDevice->config['vscu_jar_url'] ?? config('kra.vscu_jar_base_url'));
        }

        try {
            // Send the command. No strict timeout here as it's a general query.
            $response = $this->kraApi->sendCommand($endpointPath, $xmlPayload);
            $parsedXml = simplexml_load_string($response->body());

            // Parse KRA's XML response for STATUS (Section 21.7.8, Doc 1)
            // Example paths for nodes: <DATA><Snumber> etc.
            $dataNode = $parsedXml->DATA ?? null; // Ensure DATA node exists

            if (!$dataNode) {
                throw new \Exception("KRA status response missing DATA node: " . $response->body());
            }

            $kraScuId = (string) ($dataNode->Snumber ?? null);
            $firmwareVersion = (string) ($dataNode->FWver ?? null);
            $hardwareRevision = (string) ($dataNode->HWrev ?? null);
            $currentZReportCount = (int) ($dataNode->CurrentZ ?? 0);
            // Dates are DD/MM/YYYY for LastLocalDate/LastRemoteDate, and YYYY-MM-DD for others.
            // We'll return them as strings or parse them to ISO 8601 if needed for consistency.
            $lastRemoteAuditDate = (string) ($dataNode->LastRemoteDate ?? null);
            $lastLocalAuditDate = (string) ($dataNode->LastLocalDate ?? null);
            $kraStatus = (string) ($parsedXml->STATUS ?? 'UNKNOWN'); // 'P' or 'E'

            $operationalStatus = ($kraStatus === 'P') ? 'OPERATIONAL' : 'ERROR';
            $errorMessage = ($kraStatus === 'E') ? (string)($dataNode->ErrorCode ?? 'Unknown KRA error detail.') : null;

            // Update local KraDevice status and last check time
            $kraDevice->status = ($operationalStatus === 'OPERATIONAL') ? 'ACTIVATED' : 'ERROR';
            $kraDevice->last_status_check_at = now();
            $kraDevice->save();

            return [
                'kraScuId' => $kraScuId,
                'firmwareVersion' => $firmwareVersion,
                'hardwareRevision' => $hardwareRevision,
                'currentZReportCount' => $currentZReportCount,
                'lastRemoteAuditDate' => $lastRemoteAuditDate,
                'lastLocalAuditDate' => $lastLocalAuditDate,
                'operationalStatus' => $operationalStatus,
                'errorMessage' => $errorMessage,
            ];

        } catch (KraApiException $e) {
            // If KRA device returns 'E' status, update local device to 'ERROR'
            $kraDevice->status = 'ERROR';
            $kraDevice->last_status_check_at = now();
            $kraDevice->save();
            throw $e;
        } finally {
            // Ensure baseUrl is restored
            $this->kraApi->setBaseUrl($originalBaseUrl);
        }
        throw new \Exception('getDeviceStatus: Unexpected error, no status returned.');
    }

    /**
     * Helper to extract KRA SCU ID from KRA's error response (e.g., for error code 41).
     * This assumes the SCU ID might be present in a specific node within an error XML.
     * You might need to adjust the XPath based on actual KRA error XML examples.
     * For example, if it's just in the raw text, you'd use regex.
     * @param string $rawResponse The raw XML error response from KRA.
     * @return string|null The extracted KRA SCU ID, or null if not found.
     */
    private function extractKraScuIdFromErrorResponse(string $rawResponse): ?string
    {
        try {
            $xml = simplexml_load_string($rawResponse);
            // This path is highly dependent on KRA's actual error XML structure.
            // For KRA error 41, the response often mentions the ID.
            // Example: If the error message is "Device KRACU12345 is already activated."
            // You might need a regex on $rawResponse instead of XML parsing.
            // For now, let's assume it's in a node or a simple regex is needed.
            preg_match('/Device ([A-Z0-9]+) is already activated/', $rawResponse, $matches);
            if (isset($matches[1])) {
                return $matches[1];
            }
            return null; // Or try other parsing methods
        } catch (\Throwable $e) {
            logger()->warning("Failed to extract KRA SCU ID from error response: " . $e->getMessage(), ['raw_response' => $rawResponse]);
            return null;
        }
        return null;
    }
} 