<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraPurchaseService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class KraPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private KraPurchaseService $kraPurchaseService;
    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        $this->kraPurchaseService = new KraPurchaseService($this->mockKraApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_purchase_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $purchaseData = [
            'kra_device_id' => $kraDevice->id,
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
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

        $this->mockKraApi->shouldReceive('sendPurchase')
            ->once()
            ->with(Mockery::subset($purchaseData))
            ->andReturn($mockKraResponse);

        $result = $this->kraPurchaseService->sendPurchase($purchaseData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
    }

    public function test_send_purchase_device_not_found()
    {
        $purchaseData = [
            'kra_device_id' => 'non-existent-device',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraPurchaseService->sendPurchase($purchaseData);
    }

    public function test_send_purchase_device_not_activated()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'PENDING' // Not activated
        ]);

        $purchaseData = [
            'kra_device_id' => $kraDevice->id,
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device is not activated');

        $this->kraPurchaseService->sendPurchase($purchaseData);
    }

    public function test_send_purchase_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $purchaseData = [
            'kra_device_id' => $kraDevice->id,
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => []
        ];

        $this->mockKraApi->shouldReceive('sendPurchase')
            ->once()
            ->andThrow(new KraApiException('Purchase submission failed', 1008));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Purchase submission failed');

        $this->kraPurchaseService->sendPurchase($purchaseData);
    }

    public function test_build_purchase_xml()
    {
        $xml = $this->kraPurchaseService->buildPurchaseXml([
            'kra_device_id' => 'KRACU0100000001',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
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
        $this->assertStringContainsString('<PurchaseNumber>PUR001</PurchaseNumber>', $xml);
        $this->assertStringContainsString('<SupplierPIN>A987654321B</SupplierPIN>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item 1</ItemName>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item 2</ItemName>', $xml);
        $this->assertStringContainsString('<Quantity>10</Quantity>', $xml);
        $this->assertStringContainsString('<Quantity>5</Quantity>', $xml);
        $this->assertStringContainsString('<UnitPrice>100.00</UnitPrice>', $xml);
        $this->assertStringContainsString('<UnitPrice>50.00</UnitPrice>', $xml);
    }

    public function test_validate_purchase_data_success()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 10,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $result = $this->kraPurchaseService->validatePurchaseData($purchaseData);

        $this->assertTrue($result);
    }

    public function test_validate_purchase_data_missing_device()
    {
        $purchaseData = [
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device ID is required');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }

    public function test_validate_purchase_data_missing_purchase_number()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'supplier_pin' => 'A987654321B',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Purchase number is required');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }

    public function test_validate_purchase_data_missing_supplier_pin()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'purchase_number' => 'PUR001',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Supplier PIN is required');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }

    public function test_validate_purchase_data_empty_items()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('At least one item is required');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }

    public function test_validate_purchase_data_invalid_supplier_pin()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'INVALID',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 10,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid supplier PIN format');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }

    public function test_validate_purchase_data_negative_quantity()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => -5,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Item quantity must be positive');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }

    public function test_validate_purchase_data_invalid_price()
    {
        $purchaseData = [
            'kra_device_id' => 'test-device',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A987654321B',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 10,
                    'unit_price' => -100.00
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Item unit price must be positive');

        $this->kraPurchaseService->validatePurchaseData($purchaseData);
    }
} 