<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraPurchaseService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Illuminate\Http\Client\Response;

class KraPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $kraPurchaseService;
    private $kraDevice;
    private $taxpayerPin;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('kra.simulation_mode', true);
        
        $this->taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $this->kraDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED',
            'device_type' => 'OSCU'
        ]);

        $this->kraApiMock = Mockery::mock(KraApi::class);
        $this->kraApiMock->shouldReceive('getBaseUrl')->andReturn('http://test.com');
        $this->kraApiMock->shouldReceive('setBaseUrl')->zeroOrMoreTimes()->andReturnNull();
        $this->kraPurchaseService = new KraPurchaseService($this->kraApiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_purchase_success()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockPurchaseResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $purchaseData = [
            'purchaseDate' => '2024-01-15',
            'supplierPin' => 'B987654321A',
            'supplierName' => 'Supplier Company Ltd',
            'invoiceNumber' => 'INV001',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalAmount' => 5000.00,
                    'taxRate' => 16.00,
                    'taxAmount' => 800.00
                ]
            ]
        ];

        $result = $this->kraPurchaseService->sendPurchase($this->kraDevice, $purchaseData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('PUR001', $result['purchaseId']);
    }

    public function test_send_purchase_missing_required_fields()
    {
        $purchaseData = [
            'purchaseDate' => '2024-01-15'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Supplier PIN is required');

        $this->kraPurchaseService->sendPurchase($this->kraDevice, $purchaseData);
    }

    public function test_send_purchase_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Purchase submission failed', 'PURCHASE_ERROR', 'Error response'));

        $purchaseData = [
            'purchaseDate' => '2024-01-15',
            'supplierPin' => 'B987654321A',
            'supplierName' => 'Supplier Company Ltd',
            'invoiceNumber' => 'INV001',
            'items' => [
                [
                    'itemCode' => 'PROD001',
                    'itemName' => 'Test Product',
                    'quantity' => 100,
                    'unitPrice' => 50.00,
                    'totalAmount' => 5000.00,
                    'taxRate' => 16.00,
                    'taxAmount' => 800.00
                ]
            ]
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Purchase submission failed');

        $this->kraPurchaseService->sendPurchase($this->kraDevice, $purchaseData);
    }

    private function getMockPurchaseResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <PurchaseId>PUR001</PurchaseId>
        <PurchaseDate>2024-01-15</PurchaseDate>
        <SubmissionDate>15/01/2024</SubmissionDate>
        <SubmissionTime>14:30:25</SubmissionTime>
        <Status>SUBMITTED</Status>
    </DATA>
</KRA>';
    }
} 