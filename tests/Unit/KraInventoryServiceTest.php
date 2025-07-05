<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraInventoryService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class KraInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private KraInventoryService $kraInventoryService;
    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        $this->kraInventoryService = new KraInventoryService($this->mockKraApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_inventory_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $inventoryData = [
            'kra_device_id' => $kraDevice->id,
            'items' => [
                [
                    'item_name' => 'Test Item 1',
                    'quantity' => 10,
                    'unit_price' => 100.00
                ],
                [
                    'item_name' => 'Test Item 2',
                    'quantity' => 5,
                    'unit_price' => 50.00
                ]
            ]
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendInventory')
            ->once()
            ->with(Mockery::subset($inventoryData))
            ->andReturn($mockKraResponse);

        $result = $this->kraInventoryService->sendInventory($inventoryData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
    }

    public function test_send_inventory_device_not_found()
    {
        $inventoryData = [
            'kra_device_id' => 'non-existent-device',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraInventoryService->sendInventory($inventoryData);
    }

    public function test_send_inventory_device_not_activated()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'PENDING' // Not activated
        ]);

        $inventoryData = [
            'kra_device_id' => $kraDevice->id,
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device is not activated');

        $this->kraInventoryService->sendInventory($inventoryData);
    }

    public function test_send_inventory_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $inventoryData = [
            'kra_device_id' => $kraDevice->id,
            'items' => []
        ];

        $this->mockKraApi->shouldReceive('sendInventory')
            ->once()
            ->andThrow(new KraApiException('Inventory submission failed', 1006));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Inventory submission failed');

        $this->kraInventoryService->sendInventory($inventoryData);
    }

    public function test_send_inventory_movement_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $movementData = [
            'kra_device_id' => $kraDevice->id,
            'movement_type' => 'IN',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 5,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendInventoryMovement')
            ->once()
            ->with(Mockery::subset($movementData))
            ->andReturn($mockKraResponse);

        $result = $this->kraInventoryService->sendInventoryMovement($movementData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
    }

    public function test_send_inventory_movement_out()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $movementData = [
            'kra_device_id' => $kraDevice->id,
            'movement_type' => 'OUT',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => -3,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendInventoryMovement')
            ->once()
            ->with(Mockery::subset($movementData))
            ->andReturn($mockKraResponse);

        $result = $this->kraInventoryService->sendInventoryMovement($movementData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
    }

    public function test_send_inventory_movement_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $movementData = [
            'kra_device_id' => $kraDevice->id,
            'movement_type' => 'IN',
            'items' => []
        ];

        $this->mockKraApi->shouldReceive('sendInventoryMovement')
            ->once()
            ->andThrow(new KraApiException('Inventory movement failed', 1007));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Inventory movement failed');

        $this->kraInventoryService->sendInventoryMovement($movementData);
    }

    public function test_build_inventory_xml()
    {
        $xml = $this->kraInventoryService->buildInventoryXml([
            'kra_device_id' => 'KRACU0100000001',
            'items' => [
                [
                    'item_name' => 'Test Item 1',
                    'quantity' => 10,
                    'unit_price' => 100.00
                ],
                [
                    'item_name' => 'Test Item 2',
                    'quantity' => 5,
                    'unit_price' => 50.00
                ]
            ]
        ]);

        $this->assertStringContainsString('<DeviceId>KRACU0100000001</DeviceId>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item 1</ItemName>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item 2</ItemName>', $xml);
        $this->assertStringContainsString('<Quantity>10</Quantity>', $xml);
        $this->assertStringContainsString('<Quantity>5</Quantity>', $xml);
        $this->assertStringContainsString('<UnitPrice>100.00</UnitPrice>', $xml);
        $this->assertStringContainsString('<UnitPrice>50.00</UnitPrice>', $xml);
    }

    public function test_build_inventory_movement_xml()
    {
        $xml = $this->kraInventoryService->buildInventoryMovementXml([
            'kra_device_id' => 'KRACU0100000001',
            'movement_type' => 'IN',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 5,
                    'unit_price' => 100.00
                ]
            ]
        ]);

        $this->assertStringContainsString('<DeviceId>KRACU0100000001</DeviceId>', $xml);
        $this->assertStringContainsString('<MovementType>IN</MovementType>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item</ItemName>', $xml);
        $this->assertStringContainsString('<Quantity>5</Quantity>', $xml);
        $this->assertStringContainsString('<UnitPrice>100.00</UnitPrice>', $xml);
    }

    public function test_build_inventory_movement_xml_out()
    {
        $xml = $this->kraInventoryService->buildInventoryMovementXml([
            'kra_device_id' => 'KRACU0100000001',
            'movement_type' => 'OUT',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => -3,
                    'unit_price' => 100.00
                ]
            ]
        ]);

        $this->assertStringContainsString('<MovementType>OUT</MovementType>', $xml);
        $this->assertStringContainsString('<Quantity>-3</Quantity>', $xml);
    }

    public function test_validate_inventory_data_success()
    {
        $inventoryData = [
            'kra_device_id' => 'test-device',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 10,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->validateInventoryData($inventoryData);

        $this->assertTrue($result);
    }

    public function test_validate_inventory_data_missing_device()
    {
        $inventoryData = [
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device ID is required');

        $this->kraInventoryService->validateInventoryData($inventoryData);
    }

    public function test_validate_inventory_data_empty_items()
    {
        $inventoryData = [
            'kra_device_id' => 'test-device',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('At least one item is required');

        $this->kraInventoryService->validateInventoryData($inventoryData);
    }

    public function test_validate_movement_data_success()
    {
        $movementData = [
            'kra_device_id' => 'test-device',
            'movement_type' => 'IN',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 5,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->validateMovementData($movementData);

        $this->assertTrue($result);
    }

    public function test_validate_movement_data_invalid_type()
    {
        $movementData = [
            'kra_device_id' => 'test-device',
            'movement_type' => 'INVALID',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Movement type must be IN or OUT');

        $this->kraInventoryService->validateMovementData($movementData);
    }
} 