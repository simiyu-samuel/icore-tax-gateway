<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraApi;
use App\Exceptions\KraApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class KraApiTest extends TestCase
{
    private KraApi $kraApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kraApi = new KraApi();
        
        // Set up test configuration
        Config::set('kra.simulation.enabled', true);
        Config::set('kra.simulation.mock_server_url', 'http://localhost:8001');
    }

    public function test_send_receipt_item_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
            <ReceiptLabel>NS</ReceiptLabel>
            <InternalData>TEST123</InternalData>
            <DigitalSignature>ABC123</DigitalSignature>
            <QRCode>https://example.com/qr</QRCode>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-receipt-item' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
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

        $result = $this->kraApi->sendReceiptItem($data);

        $this->assertIsArray($result);
        $this->assertEquals('NS', $result['receipt_label']);
        $this->assertEquals('TEST123', $result['internal_data']);
        $this->assertEquals('ABC123', $result['digital_signature']);
        $this->assertEquals('https://example.com/qr', $result['qr_code']);
    }

    public function test_send_receipt_item_kra_error()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>1001</ResponseCode>
            <ResponseMessage>Device not activated</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-receipt-item' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Device not activated');

        $this->kraApi->sendReceiptItem($data);
    }

    public function test_send_receipt_item_http_error()
    {
        Http::fake([
            'http://localhost:8001/api/mock/kra/send-receipt-item' => Http::response('Server Error', 500)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('HTTP request failed');

        $this->kraApi->sendReceiptItem($data);
    }

    public function test_send_item_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-item' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'item_name' => 'Test Item',
            'item_code' => 'ITEM001',
            'unit_price' => 100.00
        ];

        $result = $this->kraApi->sendItem($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
    }

    public function test_send_inventory_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-inventory' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 10,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $result = $this->kraApi->sendInventory($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
    }

    public function test_send_inventory_movement_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-inventory-movement' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'movement_type' => 'IN',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 5,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $result = $this->kraApi->sendInventoryMovement($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
    }

    public function test_send_purchase_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-purchase' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'purchase_number' => 'PUR001',
            'supplier_pin' => 'A123456789B',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 5,
                    'unit_price' => 100.00
                ]
            ]
        ];

        $result = $this->kraApi->sendPurchase($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
    }

    public function test_get_x_daily_report_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
            <ReportData>X Daily Report Content</ReportData>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/x-daily-report' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'report_date' => '2024-01-15'
        ];

        $result = $this->kraApi->getXDailyReport($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('X Daily Report Content', $result['report_data']);
    }

    public function test_get_z_daily_report_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
            <ReportData>Z Daily Report Content</ReportData>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/z-daily-report' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'report_date' => '2024-01-15'
        ];

        $result = $this->kraApi->getZDailyReport($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Z Daily Report Content', $result['report_data']);
    }

    public function test_get_plu_report_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
            <ReportData>PLU Report Content</ReportData>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/plu-report' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'report_date' => '2024-01-15'
        ];

        $result = $this->kraApi->getPluReport($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('PLU Report Content', $result['report_data']);
    }

    public function test_initialize_device_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
            <DeviceId>KRACU0100000001</DeviceId>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/initialize-device' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'taxpayer_pin' => 'A123456789B',
            'device_type' => 'OSCU'
        ];

        $result = $this->kraApi->initializeDevice($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('KRACU0100000001', $result['device_id']);
    }

    public function test_activate_device_success()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseCode>0</ResponseCode>
            <ResponseMessage>Success</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/activate-device' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'KRACU0100000001'
        ];

        $result = $this->kraApi->activateDevice($data);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
    }

    public function test_invalid_xml_response()
    {
        Http::fake([
            'http://localhost:8001/api/mock/kra/send-receipt-item' => Http::response('Invalid XML', 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Invalid XML response from KRA');

        $this->kraApi->sendReceiptItem($data);
    }

    public function test_missing_response_code()
    {
        $mockResponse = '<?xml version="1.0" encoding="UTF-8"?>
        <KRAeTimsResponse>
            <ResponseMessage>Success</ResponseMessage>
        </KRAeTimsResponse>';

        Http::fake([
            'http://localhost:8001/api/mock/kra/send-receipt-item' => Http::response($mockResponse, 200)
        ]);

        $data = [
            'kra_device_id' => 'test-device-123',
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'items' => []
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Invalid XML response from KRA');

        $this->kraApi->sendReceiptItem($data);
    }
} 