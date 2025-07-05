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
use Mockery;

class KraSalesServiceTest extends TestCase
{
    use RefreshDatabase;

    private KraSalesService $kraSalesService;
    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        $this->kraSalesService = new KraSalesService($this->mockKraApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_transaction_success()
    {
        // Create test data
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transactionData = [
            'kra_device_id' => $kraDevice->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'total_amount' => 200.00
                ]
            ]
        ];

        $mockKraResponse = [
            'receipt_label' => 'NS',
            'internal_data' => 'TEST123',
            'digital_signature' => 'ABC123',
            'qr_code' => 'https://example.com/qr',
            'cu_invoice_number' => 'KRACU0100000001/152'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockKraResponse);

        $result = $this->kraSalesService->processTransaction($transactionData);

        $this->assertIsArray($result);
        $this->assertEquals('NS', $result['kra_receipt_label']);
        $this->assertEquals('TEST123', $result['kra_internal_data']);
        $this->assertEquals('ABC123', $result['kra_digital_signature']);
        $this->assertEquals('https://example.com/qr', $result['kra_qr_code_url']);
        $this->assertEquals('KRACU0100000001/152', $result['kra_cu_invoice_number']);
    }

    public function test_process_transaction_credit_note_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transactionData = [
            'kra_device_id' => $kraDevice->id,
            'internal_receipt_number' => 'CN001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'CREDIT_NOTE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => -2, // Negative quantity for credit note
                    'unit_price' => 100.00,
                    'total_amount' => -200.00
                ]
            ]
        ];

        $mockKraResponse = [
            'receipt_label' => 'NC',
            'internal_data' => 'TEST123',
            'digital_signature' => 'ABC123',
            'qr_code' => 'https://example.com/qr',
            'cu_invoice_number' => 'KRACU0100000001/153'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockKraResponse);

        $result = $this->kraSalesService->processTransaction($transactionData);

        $this->assertIsArray($result);
        $this->assertEquals('NC', $result['kra_receipt_label']);
    }

    public function test_process_transaction_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transactionData = [
            'kra_device_id' => $kraDevice->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andThrow(new KraApiException('Device not activated', 1001));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device not activated');

        $this->kraSalesService->processTransaction($transactionData);
    }

    public function test_process_transaction_device_not_found()
    {
        $transactionData = [
            'kra_device_id' => 'non-existent-device',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraSalesService->processTransaction($transactionData);
    }

    public function test_process_transaction_device_not_activated()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'PENDING' // Not activated
        ]);

        $transactionData = [
            'kra_device_id' => $kraDevice->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device is not activated');

        $this->kraSalesService->processTransaction($transactionData);
    }

    public function test_build_receipt_xml_sale()
    {
        $items = [
            [
                'item_name' => 'Test Item 1',
                'quantity' => 2,
                'unit_price' => 100.00,
                'total_amount' => 200.00
            ],
            [
                'item_name' => 'Test Item 2',
                'quantity' => 1,
                'unit_price' => 50.00,
                'total_amount' => 50.00
            ]
        ];

        $xml = $this->kraSalesService->buildReceiptXml([
            'kra_device_id' => 'KRACU0100000001',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => $items
        ]);

        $this->assertStringContainsString('<ReceiptType>NORMAL</ReceiptType>', $xml);
        $this->assertStringContainsString('<TransactionType>SALE</TransactionType>', $xml);
        $this->assertStringContainsString('<InternalReceiptNumber>INV001</InternalReceiptNumber>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item 1</ItemName>', $xml);
        $this->assertStringContainsString('<ItemName>Test Item 2</ItemName>', $xml);
        $this->assertStringContainsString('<Quantity>2</Quantity>', $xml);
        $this->assertStringContainsString('<Quantity>1</Quantity>', $xml);
    }

    public function test_build_receipt_xml_credit_note()
    {
        $items = [
            [
                'item_name' => 'Test Item',
                'quantity' => -2, // Negative for credit note
                'unit_price' => 100.00,
                'total_amount' => -200.00
            ]
        ];

        $xml = $this->kraSalesService->buildReceiptXml([
            'kra_device_id' => 'KRACU0100000001',
            'internal_receipt_number' => 'CN001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'CREDIT_NOTE',
            'items' => $items
        ]);

        $this->assertStringContainsString('<TransactionType>CREDIT_NOTE</TransactionType>', $xml);
        $this->assertStringContainsString('<Quantity>-2</Quantity>', $xml);
        $this->assertStringContainsString('<TotalAmount>-200.00</TotalAmount>', $xml);
    }

    public function test_build_receipt_xml_training_mode()
    {
        $items = [
            [
                'item_name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
                'total_amount' => 100.00
            ]
        ];

        $xml = $this->kraSalesService->buildReceiptXml([
            'kra_device_id' => 'KRACU0100000001',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'TRAINING',
            'transaction_type' => 'SALE',
            'items' => $items
        ]);

        $this->assertStringContainsString('<ReceiptType>TRAINING</ReceiptType>', $xml);
    }

    public function test_build_receipt_xml_copy_mode()
    {
        $items = [
            [
                'item_name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
                'total_amount' => 100.00
            ]
        ];

        $xml = $this->kraSalesService->buildReceiptXml([
            'kra_device_id' => 'KRACU0100000001',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'COPY',
            'transaction_type' => 'SALE',
            'items' => $items
        ]);

        $this->assertStringContainsString('<ReceiptType>COPY</ReceiptType>', $xml);
    }

    public function test_build_receipt_xml_proforma_mode()
    {
        $items = [
            [
                'item_name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
                'total_amount' => 100.00
            ]
        ];

        $xml = $this->kraSalesService->buildReceiptXml([
            'kra_device_id' => 'KRACU0100000001',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'PROFORMA',
            'transaction_type' => 'SALE',
            'items' => $items
        ]);

        $this->assertStringContainsString('<ReceiptType>PROFORMA</ReceiptType>', $xml);
    }
} 