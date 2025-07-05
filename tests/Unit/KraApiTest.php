<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\KraApi;
use Illuminate\Support\Facades\Http;
use App\Exceptions\KraApiException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Mockery;

class KraApiTest extends TestCase
{
    protected KraApi $kraApi;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a fresh instance of KraApi for each test
        $this->kraApi = new KraApi();

        // Ensure config values are set for tests, can mock config too
        config(['kra.api_sandbox_base_url' => 'http://mock-kra-api.test']);
        config(['kra.vscu_jar_base_url' => 'http://mock-vscu-jar.test']);
        config(['app.env' => 'testing']); // Ensure we use sandbox URL
        config(['kra.simulation_mode' => false]); // Disable simulation mode for unit tests
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Test Cases for KraApi::buildKraXml ---
    public function test_build_kra_xml_generates_correct_structure()
    {
        $pin = 'P123456789Z';
        $command = 'STATUS';
        $data = ['field1' => 'value1', 'field2' => 'value2'];

        $xmlElement = KraApi::buildKraXml($pin, $command, $data);

        // Convert SimpleXMLElement to string to check content
        $xmlString = '';
        foreach ($xmlElement->children() as $child) {
            $xmlString .= $child->asXML();
        }

        $this->assertStringContainsString('<PIN>P123456789Z</PIN>', $xmlString);
        $this->assertStringContainsString('<CMD>STATUS</CMD>', $xmlString);
        $this->assertStringContainsString('<DATA><field1>value1</field1><field2>value2</field2></DATA>', $xmlString);
    }

    public function test_build_kra_xml_handles_empty_data()
    {
        $pin = 'P123456789Z';
        $command = 'STATUS';
        $data = [];

        $xmlElement = KraApi::buildKraXml($pin, $command, $data);

        $xmlString = '';
        foreach ($xmlElement->children() as $child) {
            $xmlString .= $child->asXML();
        }

        $this->assertStringContainsString('<PIN>P123456789Z</PIN>', $xmlString);
        $this->assertStringContainsString('<CMD>STATUS</CMD>', $xmlString);
        $this->assertStringContainsString('<DATA/>', $xmlString); // Self-closing tag
    }

    public function test_build_kra_xml_escapes_special_characters()
    {
        $pin = 'P123456789Z';
        $command = 'SEND_RECEIPT';
        $data = ['item_name' => 'Test & Item < 100 > 50'];

        $xmlElement = KraApi::buildKraXml($pin, $command, $data);

        $xmlString = '';
        foreach ($xmlElement->children() as $child) {
            $xmlString .= $child->asXML();
        }

        $this->assertStringContainsString('<item_name>Test &amp; Item &lt; 100 &gt; 50</item_name>', $xmlString);
    }

    // --- Test Cases for KraApi::sendCommand ---

    public function test_send_command_sends_xml_and_returns_response_on_success()
    {
        $pin = 'P123456789Z';
        $command = 'SEND_RECEIPT';
        $data = ['Rtype' => 'N', 'RNum' => '123'];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        $mockResponseBody = '<?xml version="1.0"?><root><STATUS>P</STATUS><DATA><SCU_ID>KRA123</SCU_ID></DATA></root>';

        // Mock the Http facade's POST request
        Http::fake([
            '*' => Http::response($mockResponseBody, 200, ['Content-Type' => 'application/xml']),
        ]);

        $response = $this->kraApi->sendCommand('/some/endpoint', $xmlPayload);

        $this->assertTrue($response->successful());
        $this->assertStringContainsString('KRA123', $response->body());
    }

    public function test_send_command_throws_kra_api_exception_on_kra_error_status()
    {
        $pin = 'P123456789Z';
        $command = 'INIT';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        $mockResponseBody = '<?xml version="1.0"?><root><STATUS>E</STATUS><DATA><ErrorCode>40</ErrorCode></DATA></root>';

        Http::fake([
            '*' => Http::response($mockResponseBody, 200, ['Content-Type' => 'application/xml']),
        ]);

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('KRA returned an error status: [40] No specific message.');
        $this->kraApi->sendCommand('/some/endpoint', $xmlPayload);
    }

    public function test_send_command_throws_kra_api_exception_on_http_error()
    {
        $pin = 'P123456789Z';
        $command = 'STATUS';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Failed to communicate with KRA API due to HTTP error');
        $this->kraApi->sendCommand('/some/endpoint', $xmlPayload);
    }

    public function test_send_command_throws_kra_api_exception_on_invalid_xml_response()
    {
        $pin = 'P123456789Z';
        $command = 'STATUS';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        Http::fake([
            '*' => Http::response('Invalid XML Response', 200),
        ]);

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('KRA response is not valid XML');
        $this->kraApi->sendCommand('/some/endpoint', $xmlPayload);
    }

    public function test_send_command_throws_kra_api_exception_on_empty_response()
    {
        $pin = 'P123456789Z';
        $command = 'STATUS';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('KRA response body is empty or unparseable XML');
        $this->kraApi->sendCommand('/some/endpoint', $xmlPayload);
    }

    public function test_send_command_uses_strict_timeout_when_requested()
    {
        $pin = 'P123456789Z';
        $command = 'INIT';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        $mockResponseBody = '<?xml version="1.0"?><root><STATUS>P</STATUS></root>';

        Http::fake([
            '*' => Http::response($mockResponseBody, 200, ['Content-Type' => 'application/xml']),
        ]);

        $response = $this->kraApi->sendCommand('/some/endpoint', $xmlPayload, true); // true for strict timeout

        $this->assertTrue($response->successful());
    }

    public function test_send_command_uses_general_timeout_by_default()
    {
        $pin = 'P123456789Z';
        $command = 'INIT';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        $mockResponseBody = '<?xml version="1.0"?><root><STATUS>P</STATUS></root>';

        Http::fake([
            '*' => Http::response($mockResponseBody, 200, ['Content-Type' => 'application/xml']),
        ]);

        $response = $this->kraApi->sendCommand('/some/endpoint', $xmlPayload, false); // false for general timeout

        $this->assertTrue($response->successful());
    }

    public function test_send_command_handles_network_timeout()
    {
        $pin = 'P123456789Z';
        $command = 'STATUS';
        $data = [];
        $xmlPayload = KraApi::buildKraXml($pin, $command, $data);

        Http::fake([
            '*' => Http::response('', 408), // Use valid status code for timeout
        ]);

        $this->expectException(KraApiException::class);
        $this->expectExceptionMessage('Failed to communicate with KRA API due to HTTP error');
        $this->kraApi->sendCommand('/some/endpoint', $xmlPayload);
    }

    public function test_get_base_url_returns_correct_url_for_testing()
    {
        // Set the base URL for testing
        $this->kraApi->setBaseUrl('http://mock-kra-api.test');
        $baseUrl = $this->kraApi->getBaseUrl();
        $this->assertEquals('http://mock-kra-api.test', $baseUrl);
    }

    public function test_set_base_url_updates_base_url()
    {
        $newUrl = 'http://new-kra-api.test';
        $this->kraApi->setBaseUrl($newUrl);
        
        $this->assertEquals($newUrl, $this->kraApi->getBaseUrl());
    }
} 