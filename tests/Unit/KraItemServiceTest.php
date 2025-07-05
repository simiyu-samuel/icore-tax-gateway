<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraItemService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Illuminate\Http\Client\Response;

class KraItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $kraItemService;
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
        $this->kraItemService = new KraItemService($this->kraApiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_item_success()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockRegisterResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $itemData = [
            'itemName' => 'Test Product',
            'itemCode' => 'PROD001',
            'itemDescription' => 'Test product description',
            'unitPrice' => 100.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $result = $this->kraItemService->registerItem($this->kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('PROD001', $result['itemCode']);
        $this->assertNotEmpty($result['kraItemId']);
    }

    public function test_register_item_with_optional_fields()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockRegisterResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $itemData = [
            'itemName' => 'Premium Product',
            'itemCode' => 'PROD002',
            'itemDescription' => 'Premium product with detailed description',
            'unitPrice' => 500.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B',
            'itemCategory' => 'Electronics',
            'itemBrand' => 'Brand X',
            'itemModel' => 'Model Y',
            'itemSize' => 'Large',
            'itemColor' => 'Black',
            'itemWeight' => 2.5,
            'itemDimensions' => '10x20x30cm'
        ];

        $result = $this->kraItemService->registerItem($this->kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('PROD002', $result['itemCode']);
    }

    public function test_register_item_zero_tax_rate()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockRegisterResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $itemData = [
            'itemName' => 'Zero Tax Product',
            'itemCode' => 'PROD003',
            'itemDescription' => 'Product with zero tax rate',
            'unitPrice' => 50.00,
            'taxRate' => 0.00,
            'taxCategory' => 'A'
        ];

        $result = $this->kraItemService->registerItem($this->kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('PROD003', $result['itemCode']);
    }

    public function test_register_item_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Item registration failed', 'REGISTER_ERROR', 'Error response'));

        $itemData = [
            'itemName' => 'Test Product',
            'itemCode' => 'PROD001',
            'itemDescription' => 'Test product description',
            'unitPrice' => 100.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Item registration failed');

        $this->kraItemService->registerItem($this->kraDevice, $itemData);
    }

    public function test_register_item_missing_required_fields()
    {
        $itemData = [
            'itemName' => 'Test Product'
            // Missing required fields
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item code is required');

        $this->kraItemService->registerItem($this->kraDevice, $itemData);
    }

    public function test_register_item_invalid_tax_category()
    {
        $itemData = [
            'itemName' => 'Test Product',
            'itemCode' => 'PROD001',
            'itemDescription' => 'Test product description',
            'unitPrice' => 100.00,
            'taxRate' => 16.00,
            'taxCategory' => 'INVALID'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tax category: INVALID');

        $this->kraItemService->registerItem($this->kraDevice, $itemData);
    }

    public function test_register_item_negative_unit_price()
    {
        $itemData = [
            'itemName' => 'Test Product',
            'itemCode' => 'PROD001',
            'itemDescription' => 'Test product description',
            'unitPrice' => -100.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit price must be non-negative');

        $this->kraItemService->registerItem($this->kraDevice, $itemData);
    }

    public function test_register_item_negative_tax_rate()
    {
        $itemData = [
            'itemName' => 'Test Product',
            'itemCode' => 'PROD001',
            'itemDescription' => 'Test product description',
            'unitPrice' => 100.00,
            'taxRate' => -16.00,
            'taxCategory' => 'B'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax rate must be non-negative');

        $this->kraItemService->registerItem($this->kraDevice, $itemData);
    }

    public function test_register_item_vscu_device()
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
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockRegisterResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $itemData = [
            'itemName' => 'VSCU Product',
            'itemCode' => 'VSCU001',
            'itemDescription' => 'Product for VSCU device',
            'unitPrice' => 200.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $result = $this->kraItemService->registerItem($vscuDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('VSCU001', $result['itemCode']);
    }

    public function test_register_item_missing_data_node()
    {
        // Mock KRA response without DATA node
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $itemData = [
            'itemName' => 'Test Product',
            'itemCode' => 'PROD001',
            'itemDescription' => 'Test product description',
            'unitPrice' => 100.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing DATA node in KRA response');

        $this->kraItemService->registerItem($this->kraDevice, $itemData);
    }

    public function test_register_item_with_special_characters()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockRegisterResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $itemData = [
            'itemName' => 'Product with Special Chars: @#$%^&*()',
            'itemCode' => 'PROD-SPECIAL-001',
            'itemDescription' => 'Product with special characters & symbols',
            'unitPrice' => 150.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $result = $this->kraItemService->registerItem($this->kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('PROD-SPECIAL-001', $result['itemCode']);
    }

    public function test_register_item_long_description()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockRegisterResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $longDescription = str_repeat('This is a very long description that tests the maximum length handling. ', 10);
        
        $itemData = [
            'itemName' => 'Long Description Product',
            'itemCode' => 'PROD-LONG-001',
            'itemDescription' => $longDescription,
            'unitPrice' => 75.00,
            'taxRate' => 16.00,
            'taxCategory' => 'B'
        ];

        $result = $this->kraItemService->registerItem($this->kraDevice, $itemData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('PROD-LONG-001', $result['itemCode']);
    }

    private function getMockRegisterResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <ItemCode>PROD001</ItemCode>
        <KraItemId>KRA_ITEM_123456</KraItemId>
        <RegistrationDate>15/01/2024</RegistrationDate>
        <RegistrationTime>14:30:25</RegistrationTime>
        <Status>REGISTERED</Status>
    </DATA>
</KRA>';
    }
} 