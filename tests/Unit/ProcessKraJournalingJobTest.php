<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Jobs\ProcessKraJournalingJob;
use App\Models\Transaction;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Services\KraApi;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Mockery;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\Response;

class ProcessKraJournalingJobTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $taxpayerPin;
    private $kraDevice;
    private $transaction;

    protected function setUp(): void
    {
        parent::setUp();
        
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

        $this->transaction = Transaction::factory()->for($this->kraDevice)->create([
            'internal_receipt_number' => 'INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING',
            'kra_digital_signature' => 'SIGNATURE123',
            'kra_qr_code_url' => 'https://qr.kra.go.ke/123'
        ]);

        // Mock KraApi
        $this->kraApiMock = Mockery::mock(KraApi::class);
        $this->kraApiMock->shouldReceive('getBaseUrl')->andReturn('http://test.com');
        $this->kraApiMock->shouldReceive('setBaseUrl')->zeroOrMoreTimes()->andReturnNull();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_success()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockJournalResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('COMPLETED', $this->transaction->journal_status);
        $this->assertNotNull($this->transaction->journaled_at);
    }

    public function test_handle_transaction_not_found()
    {
        $job = new ProcessKraJournalingJob(99999);
        
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $job->handle($this->kraApiMock);
    }

    public function test_handle_already_journaled()
    {
        $this->transaction->update([
            'journal_status' => 'COMPLETED',
            'journaled_at' => now()
        ]);

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('COMPLETED', $this->transaction->journal_status);
    }

    public function test_handle_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Journaling failed', 'JOURNAL_ERROR', 'Error response'));

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('FAILED', $this->transaction->journal_status);
        $this->assertNotNull($this->transaction->journal_error);
    }

    public function test_handle_missing_digital_signature()
    {
        $this->transaction->update(['kra_digital_signature' => null]);

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('FAILED', $this->transaction->journal_status);
        $this->assertStringContainsString('Missing digital signature', $this->transaction->journal_error);
    }

    public function test_handle_missing_qr_code()
    {
        $this->transaction->update(['kra_qr_code_url' => null]);

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('FAILED', $this->transaction->journal_status);
        $this->assertStringContainsString('Missing QR code URL', $this->transaction->journal_error);
    }

    public function test_handle_credit_note_transaction()
    {
        $creditTransaction = Transaction::factory()->for($this->kraDevice)->create([
            'internal_receipt_number' => 'CN001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'CREDIT_NOTE',
            'journal_status' => 'PENDING',
            'kra_digital_signature' => 'SIGNATURE456',
            'kra_qr_code_url' => 'https://qr.kra.go.ke/456'
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockJournalResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $job = new ProcessKraJournalingJob($creditTransaction->id);
        $job->handle($this->kraApiMock);

        $creditTransaction->refresh();
        $this->assertEquals('COMPLETED', $creditTransaction->journal_status);
    }

    public function test_handle_vscu_device()
    {
        $vscuDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000002',
            'status' => 'ACTIVATED',
            'device_type' => 'VSCU',
            'config' => ['vscu_jar_url' => 'http://vscu.local:8080']
        ]);

        $vscuTransaction = Transaction::factory()->for($vscuDevice)->create([
            'internal_receipt_number' => 'VSCU-INV001',
            'receipt_type' => 'NORMAL',
            'transaction_type' => 'SALE',
            'journal_status' => 'PENDING',
            'kra_digital_signature' => 'VSCU-SIGNATURE',
            'kra_qr_code_url' => 'https://qr.kra.go.ke/vscu-123'
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockJournalResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $job = new ProcessKraJournalingJob($vscuTransaction->id);
        $job->handle($this->kraApiMock);

        $vscuTransaction->refresh();
        $this->assertEquals('COMPLETED', $vscuTransaction->journal_status);
    }

    public function test_handle_missing_data_node()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('FAILED', $this->transaction->journal_status);
        $this->assertStringContainsString('Missing DATA node', $this->transaction->journal_error);
    }

    public function test_handle_retry_failed_transaction()
    {
        $this->transaction->update([
            'journal_status' => 'FAILED',
            'journal_error' => 'Previous error'
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockJournalResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $job = new ProcessKraJournalingJob($this->transaction->id);
        $job->handle($this->kraApiMock);

        $this->transaction->refresh();
        $this->assertEquals('COMPLETED', $this->transaction->journal_status);
        $this->assertNull($this->transaction->journal_error);
    }

    private function getMockJournalResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <JournalId>JOURNAL_123456</JournalId>
        <TransactionId>INV001</TransactionId>
        <JournalDate>15/01/2024</JournalDate>
        <JournalTime>14:30:25</JournalTime>
        <Status>JOURNALED</Status>
    </DATA>
</KRA>';
    }
} 