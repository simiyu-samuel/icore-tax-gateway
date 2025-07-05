<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\ApiClient;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Models\Transaction;
use App\Services\KraSalesService;
use App\Services\KraApi;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;

class KraSalesControllerTest extends TestCase
{
    use RefreshDatabase;

    private $mockKraSalesService;
    private $mockKraApi;
    private $apiClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraSalesService = Mockery::mock(KraSalesService::class);
        $this->mockKraApi = Mockery::mock(KraApi::class);
        
        $this->app->instance(KraSalesService::class, $this->mockKraSalesService);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        // Create API client for authentication
        $this->apiClient = ApiClient::factory()->create([
            'name' => 'Test Client',
            'api_key' => Hash::make('test-api-key'),
            'is_active' => true,
            'allowed_taxpayer_pins' => 'A123456789B'
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_transaction_success()
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
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'total_amount' => 200.00
                ]
            ]
        ];

        $mockResponse = [
            'kra_receipt_label' => 'NS',
            'kra_internal_data' => 'TEST123',
            'kra_digital_signature' => 'ABC123',
            'kra_qr_code_url' => 'https://example.com/qr',
            'kra_cu_invoice_number' => 'KRACU0100000001/152'
        ];

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockResponse);

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'kra_receipt_label' => 'NS',
                    'kra_internal_data' => 'TEST123',
                    'kra_digital_signature' => 'ABC123',
                    'kra_qr_code_url' => 'https://example.com/qr',
                    'kra_cu_invoice_number' => 'KRACU0100000001/152'
                ]
            ]);
    }

    public function test_process_transaction_missing_api_key()
    {
        $response = $this->postJson('/api/v1/transactions', []);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'API Key missing.'
            ]);
    }

    public function test_process_transaction_invalid_api_key()
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'invalid-key'
        ])->postJson('/api/v1/transactions', []);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid API Key.'
            ]);
    }

    public function test_process_transaction_validation_error()
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kra_device_id', 'internal_receipt_number', 'receipt_type', 'transaction_type', 'items']);
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

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->andThrow(new KraApiException('Device not activated', 1001));

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Device not activated'
            ]);
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

        $mockResponse = [
            'kra_receipt_label' => 'NC',
            'kra_internal_data' => 'TEST123',
            'kra_digital_signature' => 'ABC123',
            'kra_qr_code_url' => 'https://example.com/qr',
            'kra_cu_invoice_number' => 'KRACU0100000001/153'
        ];

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockResponse);

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'kra_receipt_label' => 'NC'
                ]
            ]);
    }

    public function test_process_transaction_negative_unit_price_credit_note()
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
                    'quantity' => 2,
                    'unit_price' => -100.00, // Negative unit price for credit note
                    'total_amount' => -200.00
                ]
            ]
        ];

        $mockResponse = [
            'kra_receipt_label' => 'NC',
            'kra_internal_data' => 'TEST123',
            'kra_digital_signature' => 'ABC123',
            'kra_qr_code_url' => 'https://example.com/qr',
            'kra_cu_invoice_number' => 'KRACU0100000001/153'
        ];

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockResponse);

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'kra_receipt_label' => 'NC'
                ]
            ]);
    }

    public function test_process_transaction_training_mode()
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
            'receipt_type' => 'TRAINING',
            'transaction_type' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $mockResponse = [
            'kra_receipt_label' => 'NS',
            'kra_internal_data' => 'TEST123',
            'kra_digital_signature' => 'ABC123',
            'kra_qr_code_url' => 'https://example.com/qr',
            'kra_cu_invoice_number' => 'KRACU0100000001/152'
        ];

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockResponse);

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);
    }

    public function test_process_transaction_copy_mode()
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
            'receipt_type' => 'COPY',
            'transaction_type' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $mockResponse = [
            'kra_receipt_label' => 'NS',
            'kra_internal_data' => 'TEST123',
            'kra_digital_signature' => 'ABC123',
            'kra_qr_code_url' => 'https://example.com/qr',
            'kra_cu_invoice_number' => 'KRACU0100000001/152'
        ];

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockResponse);

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);
    }

    public function test_process_transaction_proforma_mode()
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
            'receipt_type' => 'PROFORMA',
            'transaction_type' => 'SALE',
            'items' => [
                [
                    'item_name' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'total_amount' => 100.00
                ]
            ]
        ];

        $mockResponse = [
            'kra_receipt_label' => 'NS',
            'kra_internal_data' => 'TEST123',
            'kra_digital_signature' => 'ABC123',
            'kra_qr_code_url' => 'https://example.com/qr',
            'kra_cu_invoice_number' => 'KRACU0100000001/152'
        ];

        $this->mockKraSalesService->shouldReceive('processTransaction')
            ->once()
            ->with(Mockery::subset($transactionData))
            ->andReturn($mockResponse);

        $response = $this->withHeaders([
            'X-API-Key' => 'test-api-key'
        ])->postJson('/api/v1/transactions', $transactionData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);
    }
} 