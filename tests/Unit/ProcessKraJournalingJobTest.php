<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Jobs\ProcessKraJournalingJob;
use App\Services\KraApi;
use App\Models\Transaction;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;

class ProcessKraJournalingJobTest extends TestCase
{
    use RefreshDatabase;

    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_processes_transaction_successfully()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING'
        ]);

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andReturn($mockKraResponse);

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status was updated
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'COMPLETED'
        ]);
    }

    public function test_job_handles_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING'
        ]);

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andThrow(new KraApiException('Device not activated', 1001));

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status was updated to failed
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'FAILED',
            'journal_error_message' => 'Device not activated'
        ]);
    }

    public function test_job_retries_on_failure()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING'
        ]);

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andThrow(new KraApiException('Temporary error', 1002));

        $job = new ProcessKraJournalingJob($transaction);
        
        // Simulate job failure and retry
        $job->failed(new KraApiException('Temporary error', 1002));

        // Verify transaction status remains pending for retry
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'PENDING'
        ]);
    }

    public function test_job_handles_already_completed_transaction()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'COMPLETED' // Already completed
        ]);

        // Should not call KRA API for already completed transaction
        $this->mockKraApi->shouldNotReceive('sendReceiptItem');

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status remains completed
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'COMPLETED'
        ]);
    }

    public function test_job_handles_failed_transaction()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'FAILED' // Already failed
        ]);

        // Should not call KRA API for already failed transaction
        $this->mockKraApi->shouldNotReceive('sendReceiptItem');

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status remains failed
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'FAILED'
        ]);
    }

    public function test_job_handles_credit_note_transaction()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'CN001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'CREDIT_NOTE',
            'journal_status' => 'PENDING'
        ]);

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andReturn($mockKraResponse);

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status was updated
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'COMPLETED'
        ]);
    }

    public function test_job_handles_training_transaction()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'TRAINING',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING'
        ]);

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andReturn($mockKraResponse);

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status was updated
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'COMPLETED'
        ]);
    }

    public function test_job_handles_copy_transaction()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'COPY',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING'
        ]);

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andReturn($mockKraResponse);

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status was updated
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'COMPLETED'
        ]);
    }

    public function test_job_handles_proforma_transaction()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $transaction = Transaction::factory()->create([
            'kra_device_id' => $kraDevice->id,
            'taxpayer_pin_id' => $taxpayerPin->id,
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'PROFORMA',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING'
        ]);

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success'
        ];

        $this->mockKraApi->shouldReceive('sendReceiptItem')
            ->once()
            ->andReturn($mockKraResponse);

        $job = new ProcessKraJournalingJob($transaction);
        $job->handle($this->mockKraApi);

        // Verify transaction status was updated
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'journal_status' => 'COMPLETED'
        ]);
    }
} 