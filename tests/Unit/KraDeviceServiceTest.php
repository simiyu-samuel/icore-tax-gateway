<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraDeviceService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Illuminate\Http\Client\Response;

class KraDeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $kraDeviceService;
    private $taxpayerPin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable simulation mode for testing
        Config::set('kra.simulation_mode', true);
        
        // Create real models with factories
        $this->taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        // Mock KraApi
        $this->kraApiMock = Mockery::mock(KraApi::class);
        // Default expectations to prevent Mockery exceptions
        $this->kraApiMock->shouldReceive('getBaseUrl')->andReturn('http://test.com');
        $this->kraApiMock->shouldReceive('setBaseUrl')->zeroOrMoreTimes()->andReturnNull();
        $this->kraDeviceService = new KraDeviceService($this->kraApiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initialize_device_success()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInitializeResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $deviceData = [
            'taxpayerPin' => 'A123456789B',
            'branchOfficeId' => 'BR001',
            'deviceType' => 'OSCU'
        ];

        $result = $this->kraDeviceService->initializeDevice($deviceData);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertEquals($this->taxpayerPin->id, $result->taxpayer_pin_id);
        $this->assertEquals('KRACU0100000001', $result->kra_scu_id);
        $this->assertEquals('OSCU', $result->device_type);
        $this->assertEquals('ACTIVATED', $result->status);
    }

    public function test_initialize_device_vscu_type()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInitializeResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $deviceData = [
            'taxpayerPin' => 'A123456789B',
            'branchOfficeId' => 'BR001',
            'deviceType' => 'VSCU'
        ];

        $result = $this->kraDeviceService->initializeDevice($deviceData);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertEquals('VSCU', $result->device_type);
        $this->assertEquals('KRACU0100000001', $result->kra_scu_id);
    }

    public function test_initialize_device_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Device initialization failed', 'INIT_ERROR', 'Error response'));

        $deviceData = [
            'taxpayerPin' => 'A123456789B',
            'branchOfficeId' => 'BR001',
            'deviceType' => 'OSCU'
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device initialization failed');

        $this->kraDeviceService->initializeDevice($deviceData);
    }

    public function test_initialize_device_invalid_taxpayer_pin()
    {
        $deviceData = [
            'taxpayerPin' => 'INVALID_PIN',
            'branchOfficeId' => 'BR001',
            'deviceType' => 'OSCU'
        ];

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->kraDeviceService->initializeDevice($deviceData);
    }

    public function test_get_device_status_success()
    {
        $kraDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockStatusResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $result = $this->kraDeviceService->getDeviceStatus($kraDevice);

        $this->assertIsArray($result);
        $this->assertEquals('KRACU0100000001', $result['kraScuId']);
        $this->assertEquals('OPERATIONAL', $result['operationalStatus']);
        $this->assertEquals('1.2.3', $result['firmwareVersion']);
        $this->assertEquals('HW001', $result['hardwareRevision']);
        $this->assertEquals(5, $result['currentZReportCount']);
    }

    public function test_get_device_status_vscu_device()
    {
        $vscuDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000002',
            'status' => 'ACTIVATED',
            'device_type' => 'VSCU',
            'config' => ['vscu_jar_url' => 'http://vscu.local:8080']
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockStatusResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $result = $this->kraDeviceService->getDeviceStatus($vscuDevice);

        $this->assertIsArray($result);
        $this->assertEquals('KRACU0100000002', $result['kraScuId']);
        $this->assertEquals('OPERATIONAL', $result['operationalStatus']);
    }

    public function test_get_device_status_missing_data_node()
    {
        $kraDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA status response missing DATA node');

        $this->kraDeviceService->getDeviceStatus($kraDevice);
    }

    public function test_get_device_status_kra_api_exception()
    {
        $kraDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Device not found', 'DEVICE_NOT_FOUND', 'Error response'));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device not found');

        $this->kraDeviceService->getDeviceStatus($kraDevice);
    }

    private function getMockInitializeResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <SCU_ID>KRACU0100000001</SCU_ID>
        <Date>15/01/2024</Date>
        <Time>14:30:25</Time>
        <DeviceStatus>ACTIVATED</DeviceStatus>
    </DATA>
</KRA>';
    }

    private function getMockStatusResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>P</STATUS>
    <DATA>
        <Snumber>KRACU0100000001</Snumber>
        <FWver>1.2.3</FWver>
        <HWrev>HW001</HWrev>
        <CurrentZ>5</CurrentZ>
        <LastRemoteDate>15/01/2024</LastRemoteDate>
        <LastLocalDate>14/01/2024</LastLocalDate>
        <ErrorCode></ErrorCode>
    </DATA>
</KRA>';
    }
} 