<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ApiClient;
use App\Models\TaxpayerPin;
use App\Models\KraDevice;
use App\Services\KraSalesService;
use App\Services\KraDeviceService;
use App\Services\KraReportService;
use App\Services\KraItemService;
use App\Services\KraInventoryService;
use App\Services\KraPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private $apiClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable simulation mode for testing
        Config::set('kra.simulation_mode', true);
        
        // Create API client for authentication (pass plain text key)
        $this->apiClient = ApiClient::factory()->create([
            'name' => 'Test Client',
            'api_key' => 'test-api-key', // plain text, let model hash it
            'is_active' => true,
            'allowed_taxpayer_pins' => 'A123456789B'
        ]);
    }

    public function test_kra_sales_service_process_transaction()
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

        $service = app(KraSalesService::class);
        $result = $service->processTransaction($kraDevice, $transactionData);

        $this->assertInstanceOf(\App\Models\Transaction::class, $result);
        $this->assertEquals($kraDevice->id, $result->kra_device_id);
    }

    public function test_kra_sales_service_process_credit_note()
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

        $service = app(KraSalesService::class);
        $result = $service->processTransaction($kraDevice, $transactionData);

        $this->assertInstanceOf(\App\Models\Transaction::class, $result);
        $this->assertEquals($kraDevice->id, $result->kra_device_id);
    }

    public function test_kra_device_service_initialize_device()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $deviceData = [
            'taxpayerPin' => 'A123456789B',
            'branchOfficeId' => 'BR001',
            'deviceType' => 'OSCU'
        ];

        $service = app(KraDeviceService::class);
        $result = $service->initializeDevice($deviceData);

        $this->assertInstanceOf(KraDevice::class, $result);
        $this->assertNotEmpty($result->kra_scu_id);
        $this->assertEquals('ACTIVATED', $result->status);
    }

    public function test_kra_device_service_get_device_status()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $service = app(KraDeviceService::class);
        $result = $service->getDeviceStatus($kraDevice);

        $this->assertArrayHasKey('kraScuId', $result);
        $this->assertArrayHasKey('operationalStatus', $result);
    }

    public function test_kra_report_service_get_x_daily_report()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $service = app(KraReportService::class);
        $result = $service->getXDailyReport($kraDevice);

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('report', $result);
    }

    public function test_kra_report_service_generate_z_daily_report()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $service = app(KraReportService::class);
        $result = $service->generateZDailyReport($kraDevice);

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('report', $result);
    }

    public function test_kra_report_service_get_plu_report()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $service = app(KraReportService::class);
        $result = $service->getPLUReport($kraDevice, '2024-01-15');

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('report', $result);
    }

    public function test_kra_item_service_register_item()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $itemData = [
            'itemCode' => 'TEST001',
            'itemClassificationCode' => '001',
            'itemName' => 'Test Item',
            'packagingUnitCode' => 'PCE',
            'quantityUnitCode' => 'PCE',
            'initialWholesaleUnitPrice' => 100.00,
            'initialQuantity' => 10,
            'defaultSellingUnitPrice' => 120.00,
            'taxType' => 'A',
            'inUse' => true,
            'useBarcode' => false,
            'changeAllowed' => true,
            'useAdditionalInfoAllowed' => false
        ];

        $service = app(KraItemService::class);
        $result = $service->registerItem($kraDevice, $itemData);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_kra_purchase_service_send_purchase()
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
            'invoiceId' => 'INV001',
            'branchId' => 'BR001',
            'supplierPin' => 'A123456789B',
            'supplierName' => 'Test Supplier',
            'supplierCuId' => 'CU001',
            'registrationTypeCode' => '01',
            'referenceId' => 'REF001',
            'paymentTypeCode' => '01',
            'invoiceStatusCode' => '01',
            'transactionDate' => '20240115',
            'registerUserId' => 'GatewaySystem',
            'registerDate' => '20240115120000',
            'totalAmount' => 1000.00,
            'totalSupplierPrice' => 900.00,
            'totalTax' => 160.00,
            'items' => [
                [
                    'sequence' => 1,
                    'itemClassificationCode' => '001',
                    'itemCode' => 'ITEM001',
                    'itemName' => 'Test Item',
                    'supplierItemClassificationCode' => '001',
                    'supplierItemCode' => 'SUP001',
                    'supplierItemName' => 'Supplier Test Item',
                    'packagingUnitCode' => 'PCE',
                    'packagingQuantity' => 1,
                    'quantityUnitCode' => 'PCE',
                    'quantity' => 5,
                    'unitPrice' => 200.00,
                    'supplierPrice' => 180.00,
                    'discountRate' => 0,
                    'discountAmount' => 0,
                    'taxableAmount' => 1000.00,
                    'taxType' => 'A',
                    'taxAmount' => 160.00
                ]
            ]
        ];

        $service = app(KraPurchaseService::class);
        $result = $service->sendPurchase($kraDevice, $purchaseData);

        $this->assertArrayHasKey('message', $result);
    }

    public function test_api_client_authentication()
    {
        // Test that the API client was created correctly
        $this->assertNotNull($this->apiClient);
        $this->assertEquals('Test Client', $this->apiClient->name);
        $this->assertTrue($this->apiClient->is_active);
        
        // Test that the API key authentication works
        $foundClient = \App\Models\ApiClient::findByApiKey('test-api-key');
        $this->assertNotNull($foundClient);
        $this->assertEquals($this->apiClient->id, $foundClient->id);
    }
} 