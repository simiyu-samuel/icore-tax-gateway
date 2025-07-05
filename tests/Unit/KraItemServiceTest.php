<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraItemService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class KraItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private KraItemService $kraItemService;
    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        $this->kraItemService = new KraItemService($this->mockKraApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_item_success()
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

        $itemData = [
            'itemCode' => 'ITEM001',
            'itemClassificationCode' => '001',
            'itemName' => 'Test Item',
            'itemTypeCode' => '2',
            'itemStandard' => '',
            'originCountryCode' => 'KE',
            'packagingUnitCode' => 'PCE',
            'quantityUnitCode' => 'PCE',
            'additionalInfo' => '0001',
            'initialWholesaleUnitPrice' => 100.00,
            'initialQuantity' => 10,
            'averageWholesaleUnitPrice' => 100.00,
            'defaultSellingUnitPrice' => 120.00,
            'taxType' => 'A',
            'remark' => 'Test item',
            'inUse' => true,
            'registerUserId' => 'GatewaySystem',
            'registerDate' => now()->format('YmdHis'),
            'updateUserId' => 'GatewaySystem',
            'updateDate' => now()->format('YmdHis'),
            'safetyQuantity' => 0,
            'useBarcode' => false,
            'changeAllowed' => true,
            'useAdditionalInfoAllowed' => false
        ];

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('body')->andReturn('<?xml version="1.0"?><KRAeTimsResponse><STATUS>SUCCESS</STATUS></KRAeTimsResponse>');

        $this->mockKraApi->shouldReceive('sendCommand')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->kraItemService->registerItem($kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('Item registration command sent to KRA.', $result['message']);
    }

    public function test_register_item_kra_api_exception()
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

        $itemData = [
            'itemCode' => 'ITEM001',
            'itemClassificationCode' => '001',
            'itemName' => 'Test Item',
            'packagingUnitCode' => 'PCE',
            'quantityUnitCode' => 'PCE',
            'initialWholesaleUnitPrice' => 100.00,
            'initialQuantity' => 10,
            'defaultSellingUnitPrice' => 120.00,
            'taxType' => 'A',
            'inUse' => true
        ];

        $this->mockKraApi->shouldReceive('sendCommand')
            ->once()
            ->andThrow(new KraApiException('Item registration failed', 1004));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Item registration failed');

        $this->kraItemService->registerItem($kraDevice, $itemData);
    }

    public function test_get_items_success()
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

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('body')->andReturn('<?xml version="1.0"?><KRAeTimsResponse><DATA><Item><itemCd>ITEM001</itemCd><itemNm>Test Item</itemNm></Item></DATA></KRAeTimsResponse>');

        $this->mockKraApi->shouldReceive('sendCommand')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->kraItemService->getItems($kraDevice);

        $this->assertIsArray($result);
        $this->assertEquals('Item list retrieved successfully.', $result['message']);
        $this->assertIsArray($result['items']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('ITEM001', $result['items'][0]['itemCd']);
        $this->assertEquals('Test Item', $result['items'][0]['itemNm']);
    }

    public function test_get_items_kra_api_exception()
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

        $this->mockKraApi->shouldReceive('sendCommand')
            ->once()
            ->andThrow(new KraApiException('Failed to retrieve items', 1005));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Failed to retrieve items');

        $this->kraItemService->getItems($kraDevice);
    }

    public function test_get_items_empty_response()
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

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('body')->andReturn('<?xml version="1.0"?><KRAeTimsResponse><DATA></DATA></KRAeTimsResponse>');

        $this->mockKraApi->shouldReceive('sendCommand')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->kraItemService->getItems($kraDevice);

        $this->assertIsArray($result);
        $this->assertEquals('Item list retrieved successfully.', $result['message']);
        $this->assertIsArray($result['items']);
        $this->assertCount(0, $result['items']);
    }

    public function test_register_item_vscu_device()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'VSCU',
            'config' => ['vscu_jar_url' => 'http://localhost:8080']
        ]);

        $itemData = [
            'itemCode' => 'ITEM001',
            'itemClassificationCode' => '001',
            'itemName' => 'Test Item',
            'packagingUnitCode' => 'PCE',
            'quantityUnitCode' => 'PCE',
            'initialWholesaleUnitPrice' => 100.00,
            'initialQuantity' => 10,
            'defaultSellingUnitPrice' => 120.00,
            'taxType' => 'A',
            'inUse' => true
        ];

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('body')->andReturn('<?xml version="1.0"?><KRAeTimsResponse><STATUS>SUCCESS</STATUS></KRAeTimsResponse>');

        $this->mockKraApi->shouldReceive('sendCommand')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->kraItemService->registerItem($kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
    }
} 