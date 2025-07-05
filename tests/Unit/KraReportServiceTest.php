<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraReportService;
use App\Services\KraApi;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use App\Exceptions\KraApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Illuminate\Http\Client\Response;

class KraReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private $kraApiMock;
    private $kraReportService;
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
        $this->kraApiMock->shouldReceive('getBaseUrl')->andReturn('http://test.com');
        $this->kraApiMock->shouldReceive('setBaseUrl')->zeroOrMoreTimes()->andReturnNull();
        $this->kraReportService = new KraReportService($this->kraApiMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generate_z_report_success()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockZReportResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $reportData = [
            'reportType' => 'Z_REPORT',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-15'
        ];

        $result = $this->kraReportService->generateReport($this->kraDevice, $reportData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('Z_REPORT', $result['reportType']);
        $this->assertNotEmpty($result['reportId']);
    }

    public function test_generate_x_report_success()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockXReportResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $reportData = [
            'reportType' => 'X_REPORT',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-15'
        ];

        $result = $this->kraReportService->generateReport($this->kraDevice, $reportData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('X_REPORT', $result['reportType']);
    }

    public function test_generate_daily_report_success()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockDailyReportResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $reportData = [
            'reportType' => 'DAILY_REPORT',
            'reportDate' => '2024-01-15'
        ];

        $result = $this->kraReportService->generateReport($this->kraDevice, $reportData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('DAILY_REPORT', $result['reportType']);
    }

    public function test_generate_report_missing_required_fields()
    {
        $reportData = [
            'startDate' => '2024-01-01'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Report type is required');

        $this->kraReportService->generateReport($this->kraDevice, $reportData);
    }

    public function test_generate_report_invalid_report_type()
    {
        $reportData = [
            'reportType' => 'INVALID_REPORT',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-15'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid report type: INVALID_REPORT');

        $this->kraReportService->generateReport($this->kraDevice, $reportData);
    }

    public function test_generate_report_kra_api_exception()
    {
        $this->kraApiMock->shouldReceive('sendCommand')->andThrow(new KraApiException('Report generation failed', 'REPORT_ERROR', 'Error response'));

        $reportData = [
            'reportType' => 'Z_REPORT',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-15'
        ];

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Report generation failed');

        $this->kraReportService->generateReport($this->kraDevice, $reportData);
    }

    public function test_generate_report_missing_data_node()
    {
        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], '<KRA><STATUS>ERROR</STATUS></KRA>')
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $reportData = [
            'reportType' => 'Z_REPORT',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-15'
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing DATA node in KRA response');

        $this->kraReportService->generateReport($this->kraDevice, $reportData);
    }

    public function test_generate_report_vscu_device()
    {
        $vscuDevice = KraDevice::factory()->for($this->taxpayerPin)->create([
            'kra_scu_id' => 'KRACU0100000002',
            'status' => 'ACTIVATED',
            'device_type' => 'VSCU',
            'config' => ['vscu_jar_url' => 'http://vscu.local:8080']
        ]);

        $mockResponse = new Response(
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/xml'], $this->getMockZReportResponse())
        );

        $this->kraApiMock->shouldReceive('sendCommand')->andReturn($mockResponse);

        $reportData = [
            'reportType' => 'Z_REPORT',
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-15'
        ];

        $result = $this->kraReportService->generateReport($vscuDevice, $reportData);

        $this->assertIsArray($result);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals('Z_REPORT', $result['reportType']);
    }

    private function getMockZReportResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <ReportId>Z_REPORT_123456</ReportId>
        <ReportType>Z_REPORT</ReportType>
        <StartDate>2024-01-01</StartDate>
        <EndDate>2024-01-15</EndDate>
        <GenerationDate>15/01/2024</GenerationDate>
        <GenerationTime>14:30:25</GenerationTime>
        <Status>GENERATED</Status>
    </DATA>
</KRA>';
    }

    private function getMockXReportResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <ReportId>X_REPORT_123456</ReportId>
        <ReportType>X_REPORT</ReportType>
        <StartDate>2024-01-01</StartDate>
        <EndDate>2024-01-15</EndDate>
        <GenerationDate>15/01/2024</GenerationDate>
        <GenerationTime>14:30:25</GenerationTime>
        <Status>GENERATED</Status>
    </DATA>
</KRA>';
    }

    private function getMockDailyReportResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<KRA>
    <STATUS>SUCCESS</STATUS>
    <DATA>
        <ReportId>DAILY_REPORT_123456</ReportId>
        <ReportType>DAILY_REPORT</ReportType>
        <ReportDate>2024-01-15</ReportDate>
        <GenerationDate>15/01/2024</GenerationDate>
        <GenerationTime>14:30:25</GenerationTime>
        <Status>GENERATED</Status>
    </DATA>
</KRA>';
    }
} 