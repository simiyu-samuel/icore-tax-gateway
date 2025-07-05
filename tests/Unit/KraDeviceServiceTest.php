<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraDeviceService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class KraDeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    private KraDeviceService $kraDeviceService;
    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        $this->kraDeviceService = new KraDeviceService($this->mockKraApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initialize_device_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $deviceData = [
            'taxpayer_pin_id' => $taxpayerPin->id,
            'device_type' => 'OSCU'
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success',
            'device_id' => 'KRACU0100000001'
        ];

        $this->mockKraApi->shouldReceive('initializeDevice')
            ->once()
            ->with(Mockery::subset($deviceData))
            ->andReturn($mockKraResponse);

        $result = $this->kraDeviceService->initializeDevice($deviceData);

        $this->assertIsArray($result);
        $this->assertEquals('KRACU0100000001', $result['kra_scu_id']);
        $this->assertEquals('PENDING', $result['status']);
        $this->assertEquals('OSCU', $result['device_type']);

        // Verify device was saved to database
        $this->assertDatabaseHas('kra_devices', [
            'kra_scu_id' => 'KRACU0100000001',
            'device_type' => 'OSCU',
            'status' => 'PENDING'
        ]);
    }

    public function test_initialize_device_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $deviceData = [
            'taxpayer_pin_id' => $taxpayerPin->id,
            'device_type' => 'OSCU'
        ];

        $this->mockKraApi->shouldReceive('initializeDevice')
            ->once()
            ->andThrow(new KraApiException('Invalid taxpayer PIN', 1002));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Invalid taxpayer PIN');

        $this->kraDeviceService->initializeDevice($deviceData);
    }

    public function test_activate_device_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'PENDING'
        ]);

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('activateDevice')
            ->once()
            ->with(['kra_device_id' => $kraDevice->id])
            ->andReturn($mockKraResponse);

        $result = $this->kraDeviceService->activateDevice($kraDevice->id);

        $this->assertIsArray($result);
        $this->assertEquals('ACTIVATED', $result['status']);

        // Verify device status was updated in database
        $this->assertDatabaseHas('kra_devices', [
            'id' => $kraDevice->id,
            'status' => 'ACTIVATED'
        ]);
    }

    public function test_activate_device_not_found()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraDeviceService->activateDevice('non-existent-device');
    }

    public function test_activate_device_already_activated()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device is already activated');

        $this->kraDeviceService->activateDevice($kraDevice->id);
    }

    public function test_activate_device_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'PENDING'
        ]);

        $this->mockKraApi->shouldReceive('activateDevice')
            ->once()
            ->andThrow(new KraApiException('Device activation failed', 1003));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device activation failed');

        $this->kraDeviceService->activateDevice($kraDevice->id);
    }

    public function test_get_device_status()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        $result = $this->kraDeviceService->getDeviceStatus($kraDevice->id);

        $this->assertIsArray($result);
        $this->assertEquals('ACTIVATED', $result['status']);
        $this->assertEquals('OSCU', $result['device_type']);
        $this->assertEquals('KRACU0100000001', $result['kra_scu_id']);
    }

    public function test_get_device_status_not_found()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraDeviceService->getDeviceStatus('non-existent-device');
    }

    public function test_get_devices_by_taxpayer_pin()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice1 = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        $kraDevice2 = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000002',
            'status' => 'PENDING',
            'device_type' => 'VSCU'
        ]);

        $result = $this->kraDeviceService->getDevicesByTaxpayerPin($taxpayerPin->pin);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('KRACU0100000001', $result[0]['kra_scu_id']);
        $this->assertEquals('KRACU0100000002', $result[1]['kra_scu_id']);
    }

    public function test_get_devices_by_taxpayer_pin_not_found()
    {
        $result = $this->kraDeviceService->getDevicesByTaxpayerPin('NONEXISTENT');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_update_device_status()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $result = $this->kraDeviceService->updateDeviceStatus($kraDevice->id, 'UNAVAILABLE');

        $this->assertIsArray($result);
        $this->assertEquals('UNAVAILABLE', $result['status']);

        // Verify device status was updated in database
        $this->assertDatabaseHas('kra_devices', [
            'id' => $kraDevice->id,
            'status' => 'UNAVAILABLE'
        ]);
    }

    public function test_update_device_status_not_found()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraDeviceService->updateDeviceStatus('non-existent-device', 'ACTIVATED');
    }

    public function test_build_initialize_device_xml()
    {
        $xml = $this->kraDeviceService->buildInitializeDeviceXml([
            'taxpayer_pin' => 'A123456789B',
            'device_type' => 'OSCU'
        ]);

        $this->assertStringContainsString('<TaxpayerPIN>A123456789B</TaxpayerPIN>', $xml);
        $this->assertStringContainsString('<DeviceType>OSCU</DeviceType>', $xml);
    }

    public function test_build_activate_device_xml()
    {
        $xml = $this->kraDeviceService->buildActivateDeviceXml([
            'kra_device_id' => 'KRACU0100000001'
        ]);

        $this->assertStringContainsString('<DeviceId>KRACU0100000001</DeviceId>', $xml);
    }
} 