<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraInventoryService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Illuminate\Http\Client\Response;

class KraInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $kraInventoryService;
    private $kraDevice;
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

        $this->kraDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        // Mock KraApi
        $this->kraApiMock = Mockery::mock(KraApi::class);
        // Default expectations to prevent Mockery exceptions
        $this->kraApiMock->shouldReceive('getBaseUrl')->andReturn('http://test.com');
        $this->kraApiMock->shouldReceive('setBaseUrl')->zeroOrMoreTimes()->andReturnNull();
        $this->kraInventoryService = new KraInventoryService($this->kraApiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_inventory_success()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInventoryResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $inventoryData = [
            'inventoryDate' => '2024-01-15',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalValue' => 5000.00
                ],
                [
                    'itemCode' => 'PROD002',
                    'itemName' => 'Another Product',
                    'quantity' => 50,
                    'unitPrice' => 25.00,
                    'totalValue' => 1250.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('INV001', $result['inventoryId']);
        $this->assertEquals('2024-01-15', $result['inventoryDate']);
    }

    public function test_send_inventory_with_tax_data()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInventoryResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $inventoryData = [
            'inventoryDate' => '2024-01-15',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Taxable Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalValue' => 5000.00,
                    'taxRate' => 16.00,
                    'taxAmount' => 800.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('INV001', $result['inventoryId']);
    }

    public function test_send_inventory_missing_required_fields()
    {
        $inventoryData = [
            'inventoryDate' => '2024-01-15'
            // Missing items
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Items are required');

        $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);
    }

    public function test_send_inventory_empty_items()
    {
        $inventoryData = [
            'inventoryDate' => '2024-01-15',
            'items' => []
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item is required');

        $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);
    }

    public function test_send_inventory_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Inventory submission failed', 'INVENTORY_ERROR', 'Error response'));

        $inventoryData = [
            'inventoryDate' => '2024-01-15',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalValue' => 5000.00
                ]
            ]
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Inventory submission failed');

        $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);
    }

    public function test_send_inventory_missing_data_node()
    {
        // Mock KRA response without DATA node
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $inventoryData = [
            'inventoryDate' => '2024-01-15',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalValue' => 5000.00
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing DATA node in KRA response');

        $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);
    }

    public function test_send_inventory_movement_success()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockMovementResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $movementData = [
            'movementDate' => '2024-01-15',
            'movementType' => 'IN',
            'referenceNumber' => 'REF001',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 50,
                    'unitPrice' => 50.00,
                    'totalValue' => 2500.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->sendInventoryMovement($this->kraDevice, $movementData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('MOV001', $result['movementId']);
        $this->assertEquals('IN', $result['movementType']);
    }

    public function test_send_inventory_movement_out()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockMovementResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $movementData = [
            'movementDate' => '2024-01-15',
            'movementType' => 'OUT',
            'referenceNumber' => 'REF002',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => -25,
                    'unitPrice' => 50.00,
                    'totalValue' => -1250.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->sendInventoryMovement($this->kraDevice, $movementData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('OUT', $result['movementType']);
    }

    public function test_send_inventory_movement_invalid_type()
    {
        $movementData = [
            'movementDate' => '2024-01-15',
            'movementType' => 'INVALID',
            'referenceNumber' => 'REF001',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 50,
                    'unitPrice' => 50.00,
                    'totalValue' => 2500.00
                ]
            ]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid movement type: INVALID');

        $this->kraInventoryService->sendInventoryMovement($this->kraDevice, $movementData);
    }

    public function test_send_inventory_movement_missing_required_fields()
    {
        $movementData = [
            'movementDate' => '2024-01-15'
            // Missing required fields
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Movement type is required');

        $this->kraInventoryService->sendInventoryMovement($this->kraDevice, $movementData);
    }

    public function test_send_inventory_movement_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Movement submission failed', 'MOVEMENT_ERROR', 'Error response'));

        $movementData = [
            'movementDate' => '2024-01-15',
            'movementType' => 'IN',
            'referenceNumber' => 'REF001',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 50,
                    'unitPrice' => 50.00,
                    'totalValue' => 2500.00
                ]
            ]
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Movement submission failed');

        $this->kraInventoryService->sendInventoryMovement($this->kraDevice, $movementData);
    }

    public function test_send_inventory_movement_vscu_device()
    {
        // Create VSCU device
        $vscuDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000002',
            'status' => 'ACTIVATED',
            'device_type' => 'VSCU',
            'config' => ['vscu_jar_url' => 'http://vscu.local:8080']
        ]);

        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockMovementResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $movementData = [
            'movementDate' => '2024-01-15',
            'movementType' => 'IN',
            'referenceNumber' => 'VSCU-REF001',
            'items' => [
                [
                    'itemCode' => 'VSCU001',
                    'itemName' => 'VSCU Product',
                    'quantity' => 100,
                    'unitPrice' => 100.00,
                    'totalValue' => 10000.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->sendInventoryMovement($vscuDevice, $movementData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('MOV001', $result['movementId']);
    }

    public function test_send_inventory_with_supplier_info()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockInventoryResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $inventoryData = [
            'inventoryDate' => '2024-01-15',
            'supplierPin' => 'B987654321A',
            'supplierName' => 'Supplier Company Ltd',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalValue' => 5000.00
                ]
            ]
        ];

        $result = $this->kraInventoryService->sendInventory($this->kraDevice, $inventoryData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('INV001', $result['inventoryId']);
    }

    private function getMockInventoryResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <InventoryId>INV001</InventoryId>
        <InventoryDate>2024-01-15</InventoryDate>
        <SubmissionDate>15/01/2024</SubmissionDate>
        <SubmissionTime>14:30:25</SubmissionTime>
        <Status>SUBMITTED</Status>
    </DATA>
</KRA>';
    }

    private function getMockMovementResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <MovementId>MOV001</MovementId>
        <MovementDate>2024-01-15</MovementDate>
        <MovementType>IN</MovementType>
        <SubmissionDate>15/01/2024</SubmissionDate>
        <SubmissionTime>14:30:25</SubmissionTime>
        <Status>SUBMITTED</Status>
    </DATA>
</KRA>';
    }
} 