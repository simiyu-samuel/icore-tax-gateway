<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraReportService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class KraReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private KraReportService $kraReportService;
    private $mockKraApi;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockKraApi = Mockery::mock(KraApi::class);
        $this->app->instance(KraApi::class, $this->mockKraApi);
        
        $this->kraReportService = new KraReportService($this->mockKraApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_x_daily_report_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success',
            'report_data' => 'X Daily Report Content'
        ];

        $this->mockKraApi->shouldReceive('getXDailyReport')
            ->once()
            ->with(Mockery::subset($reportData))
            ->andReturn($mockKraResponse);

        $result = $this->kraReportService->getXDailyReport($reportData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
        $this->assertEquals('X Daily Report Content', $result['report_data']);
    }

    public function test_get_x_daily_report_device_not_found()
    {
        $reportData = [
            'kra_device_id' => 'non-existent-device',
            'report_date' => '2024-01-15'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device not found');

        $this->kraReportService->getXDailyReport($reportData);
    }

    public function test_get_x_daily_report_device_not_activated()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'PENDING' // Not activated
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device is not activated');

        $this->kraReportService->getXDailyReport($reportData);
    }

    public function test_get_x_daily_report_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $this->mockKraApi->shouldReceive('getXDailyReport')
            ->once()
            ->andThrow(new KraApiException('X Daily report failed', 1009));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('X Daily report failed');

        $this->kraReportService->getXDailyReport($reportData);
    }

    public function test_get_z_daily_report_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success',
            'report_data' => 'Z Daily Report Content'
        ];

        $this->mockKraApi->shouldReceive('getZDailyReport')
            ->once()
            ->with(Mockery::subset($reportData))
            ->andReturn($mockKraResponse);

        $result = $this->kraReportService->getZDailyReport($reportData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
        $this->assertEquals('Z Daily Report Content', $result['report_data']);
    }

    public function test_get_z_daily_report_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $this->mockKraApi->shouldReceive('getZDailyReport')
            ->once()
            ->andThrow(new KraApiException('Z Daily report failed', 1010));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Z Daily report failed');

        $this->kraReportService->getZDailyReport($reportData);
    }

    public function test_get_plu_report_success()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $mockKraResponse = [
            'response_code' => '0',
            'response_message' => 'Success',
            'report_data' => 'PLU Report Content'
        ];

        $this->mockKraApi->shouldReceive('getPluReport')
            ->once()
            ->with(Mockery::subset($reportData))
            ->andReturn($mockKraResponse);

        $result = $this->kraReportService->getPluReport($reportData);

        $this->assertIsArray($result);
        $this->assertEquals('0', $result['response_code']);
        $this->assertEquals('Success', $result['response_message']);
        $this->assertEquals('PLU Report Content', $result['report_data']);
    }

    public function test_get_plu_report_kra_api_exception()
    {
        $taxpayerPin = TaxpayerPin::factory()->create([
            'pin' => 'A123456789B'
        ]);

        $kraDevice = KraDevice::factory()->create([
            'taxpayer_pin_id' => $taxpayerPin->id,
            'kra_scu_id' => 'KRACU0100000001',
            'status' => 'ACTIVATED'
        ]);

        $reportData = [
            'kra_device_id' => $kraDevice->id,
            'report_date' => '2024-01-15'
        ];

        $this->mockKraApi->shouldReceive('getPluReport')
            ->once()
            ->andThrow(new KraApiException('PLU report failed', 1011));

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('PLU report failed');

        $this->kraReportService->getPluReport($reportData);
    }

    public function test_build_x_daily_report_xml()
    {
        $xml = $this->kraReportService->buildXDailyReportXml([
            'kra_device_id' => 'KRACU0100000001',
            'report_date' => '2024-01-15'
        ]);

        $this->assertStringContainsString('<DeviceId>KRACU0100000001</DeviceId>', $xml);
        $this->assertStringContainsString('<ReportDate>2024-01-15</ReportDate>', $xml);
    }

    public function test_build_z_daily_report_xml()
    {
        $xml = $this->kraReportService->buildZDailyReportXml([
            'kra_device_id' => 'KRACU0100000001',
            'report_date' => '2024-01-15'
        ]);

        $this->assertStringContainsString('<DeviceId>KRACU0100000001</DeviceId>', $xml);
        $this->assertStringContainsString('<ReportDate>2024-01-15</ReportDate>', $xml);
    }

    public function test_build_plu_report_xml()
    {
        $xml = $this->kraReportService->buildPluReportXml([
            'kra_device_id' => 'KRACU0100000001',
            'report_date' => '2024-01-15'
        ]);

        $this->assertStringContainsString('<DeviceId>KRACU0100000001</DeviceId>', $xml);
        $this->assertStringContainsString('<ReportDate>2024-01-15</ReportDate>', $xml);
    }

    public function test_validate_report_data_success()
    {
        $reportData = [
            'kra_device_id' => 'test-device',
            'report_date' => '2024-01-15'
        ];

        $result = $this->kraReportService->validateReportData($reportData);

        $this->assertTrue($result);
    }

    public function test_validate_report_data_missing_device()
    {
        $reportData = [
            'report_date' => '2024-01-15'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('KRA device ID is required');

        $this->kraReportService->validateReportData($reportData);
    }

    public function test_validate_report_data_missing_date()
    {
        $reportData = [
            'kra_device_id' => 'test-device'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Report date is required');

        $this->kraReportService->validateReportData($reportData);
    }

    public function test_validate_report_data_invalid_date()
    {
        $reportData = [
            'kra_device_id' => 'test-device',
            'report_date' => 'invalid-date'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid report date format');

        $this->kraReportService->validateReportData($reportData);
    }

    public function test_validate_report_data_future_date()
    {
        $reportData = [
            'kra_device_id' => 'test-device',
            'report_date' => '2025-01-15'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Report date cannot be in the future');

        $this->kraReportService->validateReportData($reportData);
    }
} 