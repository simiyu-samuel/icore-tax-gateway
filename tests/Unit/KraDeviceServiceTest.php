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
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInitializeResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $deviceData = [
            'deviceType' => 'OSCU',
            'deviceSerialNumber' => 'DEVICE123456',
            'deviceModel' => 'Model X',
            'deviceManufacturer' => 'Manufacturer Y'
        ];

        $result = $this->kraDeviceService->initializeDevice($this->taxpayerPin, $deviceData);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertEquals($this->taxpayerPin->id, $result->taxpayer_pin_id);
        $this->assertEquals('DEVICE123456', $result->device_serial_number);
        $this->assertEquals('Model X', $result->device_model);
        $this->assertEquals('Manufacturer Y', $result->device_manufacturer);
        $this->assertEquals('OSCU', $result->device_type);
        $this->assertEquals('INITIALIZED', $result->status);
        $this->assertNotEmpty($result->kra_scu_id);
    }

    public function test_initialize_device_vscu_type()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInitializeResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $deviceData = [
            'deviceType' => 'VSCU',
            'deviceSerialNumber' => 'VSCU123456',
            'deviceModel' => 'VSCU Model',
            'deviceManufacturer' => 'VSCU Manufacturer',
            'vscuJarUrl' => 'http://vscu.local:8080'
        ];

        $result = $this->kraDeviceService->initializeDevice($this->taxpayerPin, $deviceData);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertEquals('VSCU', $result->device_type);
        $this->assertEquals('VSCU123456', $result->device_serial_number);
        $this->assertArrayHasKey('vscu_jar_url', $result->config);
        $this->assertEquals('http://vscu.local:8080', $result->config['vscu_jar_url']);
    }

    public function test_initialize_device_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Device initialization failed', 'INIT_ERROR', 'Error response'));

        $deviceData = [
            'deviceType' => 'OSCU',
            'deviceSerialNumber' => 'DEVICE123456',
            'deviceModel' => 'Model X',
            'deviceManufacturer' => 'Manufacturer Y'
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device initialization failed');

        $this->kraDeviceService->initializeDevice($this->taxpayerPin, $deviceData);
    }

    public function test_initialize_device_invalid_device_type()
    {
        $deviceData = [
            'deviceType' => 'INVALID_TYPE',
            'deviceSerialNumber' => 'DEVICE123456',
            'deviceModel' => 'Model X',
            'deviceManufacturer' => 'Manufacturer Y'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid device type: INVALID_TYPE');

        $this->kraDeviceService->initializeDevice($this->taxpayerPin, $deviceData);
    }

    public function test_initialize_device_missing_required_fields()
    {
        $deviceData = [
            'deviceType' => 'OSCU'
            // Missing required fields
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Device serial number is required');

        $this->kraDeviceService->initializeDevice($this->taxpayerPin, $deviceData);
    }

    public function test_activate_device_success()
    {
        // Create an initialized device
        $device = KraDevice::factory()->for($this->taxpayerPin)->create([
            'status' => 'INITIALIZED',
            'device_type' => 'OSCU',
            'kra_scu_id' => 'KRACU0100000001'
        ]);

        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockActivateResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $result = $this->kraDeviceService->activateDevice($device);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertEquals('ACTIVATED', $result->status);
        $this->assertNotNull($result->activated_at);
    }

    public function test_activate_device_already_activated()
    {
        // Create an already activated device
        $device = KraDevice::factory()->for($this->taxpayerPin)->create([
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU',
            'kra_scu_id' => 'KRACU0100000001'
        ]);

        $result = $this->kraDeviceService->activateDevice($device);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertEquals('ACTIVATED', $result->status);
        // Should not change activation time since already activated
    }

    public function test_activate_device_not_initialized()
    {
        // Create a device that's not initialized
        $device = KraDevice::factory()->for($this->taxpayerPin)->create([
            'status' => 'PENDING',
            'device_type' => 'OSCU',
            'kra_scu_id' => 'KRACU0100000001'
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Device must be initialized before activation');

        $this->kraDeviceService->activateDevice($device);
    }

    public function test_activate_device_kra_api_exception()
    {
        // Create an initialized device
        $device = KraDevice::factory()->for($this->taxpayerPin)->create([
            'status' => 'INITIALIZED',
            'device_type' => 'OSCU',
            'kra_scu_id' => 'KRACU0100000001'
        ]);

        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Device activation failed', 'ACTIVATE_ERROR', 'Error response'));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device activation failed');

        $this->kraDeviceService->activateDevice($device);
    }

    public function test_activate_device_missing_data_node()
    {
        // Create an initialized device
        $device = KraDevice::factory()->for($this->taxpayerPin)->create([
            'status' => 'INITIALIZED',
            'device_type' => 'OSCU',
            'kra_scu_id' => 'KRACU0100000001'
        ]);

        // Mock KRA response without DATA node
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing DATA node in KRA response');

        $this->kraDeviceService->activateDevice($device);
    }

    public function test_initialize_device_with_config()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInitializeResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $deviceData = [
            'deviceType' => 'OSCU',
            'deviceSerialNumber' => 'DEVICE123456',
            'deviceModel' => 'Model X',
            'deviceManufacturer' => 'Manufacturer Y',
            'config' => [
                'custom_setting' => 'value',
                'timeout' => 30
            ]
        ];

        $result = $this->kraDeviceService->initializeDevice($this->taxpayerPin, $deviceData);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertArrayHasKey('custom_setting', $result->config);
        $this->assertEquals('value', $result->config['custom_setting']);
        $this->assertEquals(30, $result->config['timeout']);
    }

    private function getMockInitializeResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <Snumber>KRACU0100000001</Snumber>
        <Date>15/01/2024</Date>
        <Time>14:30:25</Time>
        <DeviceStatus>INITIALIZED</DeviceStatus>
    </DATA>
</KRA>';
    }

    private function getMockActivateResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <Snumber>KRACU0100000001</Snumber>
        <Date>15/01/2024</Date>
        <Time>14:30:25</Time>
        <DeviceStatus>ACTIVATED</DeviceStatus>
    </DATA>
</KRA>';
    }
} 