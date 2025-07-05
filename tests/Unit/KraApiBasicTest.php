<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraApi;
use App\Exceptions\KraApiException;
use Illuminate\Support\Facades\Config;
use SimpleXMLElement;

class KraApiBasicTest extends TestCase
{
    private KraApi $kraApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kraApi = new KraApi();
        
        // Enable simulation mode for testing
        Config::set('kra.simulation_mode', true);
    }

    public function test_build_kra_xml()
    {
        $pin = 'A123456789B';
        $command = 'SEND_RECEIPT';
        $data = [
            'DeviceId' => 'KRACU0100000001',
            'ReceiptType' => 'NORMAL',
            'TransactionType' => 'SALE'
        ];

        $xml = KraApi::buildKraXml($pin, $command, $data);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertEquals($pin, (string) $xml->PIN);
        $this->assertEquals($command, (string) $xml->CMD);
        $this->assertEquals('KRACU0100000001', (string) $xml->DATA->DeviceId);
        $this->assertEquals('NORMAL', (string) $xml->DATA->ReceiptType);
        $this->assertEquals('SALE', (string) $xml->DATA->TransactionType);
    }

    public function test_build_kra_xml_empty_data()
    {
        $pin = 'A123456789B';
        $command = 'STATUS';

        $xml = KraApi::buildKraXml($pin, $command);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertEquals($pin, (string) $xml->PIN);
        $this->assertEquals($command, (string) $xml->CMD);
    }

    public function test_build_kra_xml_with_special_characters()
    {
        $pin = 'A123456789B';
        $command = 'SEND_ITEM';
        $data = [
            'ItemName' => 'Test & Item <with> special "characters"',
            'ItemCode' => 'ITEM&001'
        ];

        $xml = KraApi::buildKraXml($pin, $command, $data);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertEquals('Test & Item <with> special "characters"', (string) $xml->DATA->ItemName);
        $this->assertEquals('ITEM&001', (string) $xml->DATA->ItemCode);
    }

    public function test_get_base_url()
    {
        $baseUrl = $this->kraApi->getBaseUrl();
        
        $this->assertIsString($baseUrl);
        $this->assertNotEmpty($baseUrl);
    }

    public function test_set_base_url()
    {
        $newUrl = 'https://test.example.com';
        $this->kraApi->setBaseUrl($newUrl);
        
        $this->assertEquals($newUrl, $this->kraApi->getBaseUrl());
    }

    public function test_send_command_simulation_mode()
    {
        $pin = 'A123456789B';
        $command = 'SEND_RECEIPT';
        $data = [
            'DeviceId' => 'KRACU0100000001',
            'ReceiptType' => 'NORMAL'
        ];

        $xml = KraApi::buildKraXml($pin, $command, $data);
        
        $response = $this->kraApi->sendCommand('/api/sendReceipt', $xml);
        
        $this->assertEquals(200, $response->status());
        $this->assertNotEmpty($response->body());
    }

    public function test_send_command_x_report()
    {
        $pin = 'A123456789B';
        $command = 'X_REPORT';
        $data = [
            'DeviceId' => 'KRACU0100000001',
            'ReportDate' => '2024-01-15'
        ];

        $xml = KraApi::buildKraXml($pin, $command, $data);
        
        $response = $this->kraApi->sendCommand('/api/xReport', $xml);
        
        $this->assertEquals(200, $response->status());
        $this->assertNotEmpty($response->body());
    }

    public function test_send_command_z_report()
    {
        $pin = 'A123456789B';
        $command = 'Z_REPORT';
        $data = [
            'DeviceId' => 'KRACU0100000001',
            'ReportDate' => '2024-01-15'
        ];

        $xml = KraApi::buildKraXml($pin, $command, $data);
        
        $response = $this->kraApi->sendCommand('/api/zReport', $xml);
        
        $this->assertEquals(200, $response->status());
        $this->assertNotEmpty($response->body());
    }

    public function test_send_command_plu_report()
    {
        $pin = 'A123456789B';
        $command = 'PLU_REPORT';
        $data = [
            'DeviceId' => 'KRACU0100000001',
            'ReportDate' => '2024-01-15'
        ];

        $xml = KraApi::buildKraXml($pin, $command, $data);
        
        $response = $this->kraApi->sendCommand('/api/pluReport', $xml);
        
        $this->assertEquals(200, $response->status());
        $this->assertNotEmpty($response->body());
    }
} 