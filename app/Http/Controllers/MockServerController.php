<?php

namespace App\Http\Controllers;

use App\Services\KraMockServerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SimpleXMLElement;

class MockServerController extends Controller
{
    protected KraMockServerService $mockServer;

    public function __construct(KraMockServerService $mockServer)
    {
        $this->mockServer = $mockServer;
    }

    /**
     * Mock KRA server endpoint for testing
     * POST /mock/kra
     */
    public function handleKraRequest(Request $request): Response
    {
        $xmlContent = $request->getContent();
        
        if (empty($xmlContent)) {
            return response('Empty request body', 400);
        }

        try {
            $xml = simplexml_load_string($xmlContent);
            if ($xml === false) {
                return response('Invalid XML', 400);
            }

            $command = (string) ($xml->CMD ?? '');
            $pin = (string) ($xml->PIN ?? '');
            
            // Extract data payload
            $dataPayload = [];
            if (isset($xml->DATA)) {
                foreach ($xml->DATA->children() as $child) {
                    $dataPayload[(string) $child->getName()] = (string) $child;
                }
            }

            // Process command and generate response
            $response = $this->mockServer->processCommand($command, $pin, $dataPayload);

            return response($response, 200, [
                'Content-Type' => 'application/xml',
            ]);

        } catch (\Exception $e) {
            return response('Error processing request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Health check endpoint
     * GET /mock/health
     */
    public function health(): Response
    {
        return response('Mock KRA Server is running', 200);
    }
} 