<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraSalesService;
use App\Services\KraApi;
use App\Models\Transaction;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Illuminate\Http\Client\Response;

class KraSalesServiceTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $kraSalesService;
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
        $this->kraSalesService = new KraSalesService($this->kraApiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_transaction_success()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockKraResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $transactionData = [
            'internalReceiptNumber' => 'INV001',
            'receiptType' => 'NORMAL',
            'transactionType' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'total_amount' => 200.00
                ]
            ]
        ];

        $result = $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($this->kraDevice->id, $result->kra_device_id);
        $this->assertEquals('INV001', $result->internal_receipt_number);
        $this->assertEquals('NORMAL', $result->receipt_type);
        $this->assertEquals('SALE', $result->transaction_type);
        $this->assertEquals('PENDING', $result->journal_status);
    }

    public function test_process_credit_note_transaction()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockKraResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $transactionData = [
            'internalReceiptNumber' => 'CN001',
            'receiptType' => 'NORMAL',
            'transactionType' => 'CREDIT_NOTE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => -2, // Negative quantity for credit note
                    'unit_price' => 100.00,
                    'total_amount' => -200.00
                ]
            ]
        ];

        $result = $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('CREDIT_NOTE', $result->transaction_type);
        $this->assertEquals('CN001', $result->internal_receipt_number);
    }

    public function test_process_transaction_with_tax_data()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockKraResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $transactionData = [
            'internalReceiptNumber' => 'INV002',
            'receiptType' => 'NORMAL',
            'transactionType' => 'SALE',
            'taxRates' => ['A' => 0.00, 'B' => 16.00],
            'taxableAmounts' => ['B' => 1000.00],
            'calculatedTaxes' => ['B' => 160.00],
            'items' => [
                [
                    'item_name' => 'Taxable Item',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'total_amount' => 1000.00
                ]
            ]
        ];

        $result = $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertNotEmpty($result->kra_digital_signature);
        $this->assertNotEmpty($result->kra_qr_code_url);
    }

    public function test_process_transaction_with_buyer_pin()
    {
        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockKraResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $transactionData = [
            'internalReceiptNumber' => 'INV003',
            'receiptType' => 'NORMAL',
            'transactionType' => 'SALE',
            'buyerPin' => 'B987654321A',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $result = $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('INV003', $result->internal_receipt_number);
    }

    public function test_process_transaction_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('KRA device offline', 'DEVICE_OFFLINE', 'Error response'));

        $transactionData = [
            'internalReceiptNumber' => 'INV004',
            'receiptType' => 'NORMAL',
            'transactionType' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('KRA device offline');

        $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);
    }

    public function test_process_transaction_invalid_receipt_type()
    {
        $transactionData = [
            'internalReceiptNumber' => 'INV005',
            'receiptType' => 'INVALID_TYPE',
            'transactionType' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid receipt type: INVALID_TYPE');

        $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);
    }

    public function test_process_transaction_invalid_transaction_type()
    {
        $transactionData = [
            'internalReceiptNumber' => 'INV006',
            'receiptType' => 'NORMAL',
            'transactionType' => 'INVALID_TYPE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid transaction type: INVALID_TYPE');

        $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);
    }

    public function test_process_transaction_vscu_device()
    {
        // Create VSCU device with valid taxpayer_pin_id
        $vscuDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $this->taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000002',
            'status' => 'ACTIVATED',
            'device_type' => 'VSCU',
            'config' => ['vscu_jar_url' => 'http://vscu.local:8080']
        ]);

        // Mock successful KRA response
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockKraResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $transactionData = [
            'internalReceiptNumber' => 'INV007',
            'receiptType' => 'NORMAL',
            'transactionType' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $result = $this->kraSalesService->processTransaction($vscuDevice, $transactionData);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($vscuDevice->id, $result->kra_device_id);
        $this->assertEquals('INV007', $result->internal_receipt_number);
    }

    public function test_process_transaction_missing_data_node()
    {
        // Mock KRA response without DATA node
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $transactionData = [
            'internalReceiptNumber' => 'INV008',
            'receiptType' => 'NORMAL',
            'transactionType' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing DATA node in KRA response');

        $this->kraSalesService->processTransaction($this->kraDevice, $transactionData);
    }

    private function getMockKraResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <Snumber>KRACU0100000001</Snumber>
        <Date>15/01/2024</Date>
        <Time>14:30:25</Time>
        <RLabel>RECEIPT_LABEL_123</RLabel>
        <TNumber>1001</TNumber>
        <GNumber>5001</GNumber>
        <Signature>DIGITAL_SIGNATURE_ABC123</Signature>
        <InternalData>INTERNAL_DATA_XYZ789</InternalData>
    </DATA>
</KRA>';
    }
} 