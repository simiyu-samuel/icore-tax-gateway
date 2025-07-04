<?php
namespace App\Services;

use App\Models\KraDevice;
use App\Exceptions\KraApiException;
use SimpleXMLElement;

class KraDeviceService
{
    protected KraApi $kraApi;

    public function __construct(KraApi $kraApi)
    {
        $this->kraApi = $kraApi;
    }

    /**
     * Initialize/activate a KRA device (OSCU or VSCU).
     *
     * @param string $pin
     * @param string $deviceType 'OSCU' or 'VSCU'
     * @param array $data Additional data for activation (e.g., device serial, etc.)
     * @return SimpleXMLElement|array
     * @throws KraApiException
     */
    public function activateDevice(string $pin, string $deviceType, array $data = [])
    {
        // The command and endpoint may vary by device type
        $command = $deviceType === 'VSCU' ? 'INIT_VSCU' : 'INIT_OSCU';
        $endpoint = $deviceType === 'VSCU' ? '/selectInitVscuInfo' : '/selectInitOsdcInfo';
        $xml = KraApi::buildKraXml($pin, $command, $data);
        $response = $this->kraApi->sendCommand($endpoint, $xml, true); // Use strict timeout for activation
        return $response->body();
    }

    /**
     * Retrieve the status of a registered KRA device.
     *
     * @param string $pin
     * @param string $deviceType 'OSCU' or 'VSCU'
     * @param array $data Additional data for status check (e.g., device serial, etc.)
     * @return SimpleXMLElement|array
     * @throws KraApiException
     */
    public function getDeviceStatus(string $pin, string $deviceType, array $data = [])
    {
        $command = 'STATUS';
        $endpoint = $deviceType === 'VSCU' ? '/selectVscuStatus' : '/selectOsdcStatus';
        $xml = KraApi::buildKraXml($pin, $command, $data);
        $response = $this->kraApi->sendCommand($endpoint, $xml, true); // Use strict timeout for status
        return $response->body();
    }
} 