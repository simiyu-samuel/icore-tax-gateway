<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\ApiClientSeeder;
use Database\Seeders\TaxpayerPinSeeder;
use Database\Seeders\ApiClientTaxpayerPinSeeder;
use App\Models\ApiClient;
use App\Models\TaxpayerPin;

class ApiClientTaxpayerPinSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_client_taxpayer_pin_seeder_creates_relationships()
    {
        // Seed the database
        $this->seed([
            TaxpayerPinSeeder::class,
            ApiClientSeeder::class,
            ApiClientTaxpayerPinSeeder::class,
        ]);

        // Retrieve the API clients
        $posClient = ApiClient::where('name', 'POS System Client')->first();
        $erpClient = ApiClient::where('name', 'ERP System Client')->first();
        $uiBackend = ApiClient::where('name', 'ICORE_UI_BACKEND')->first();

        // Retrieve the Taxpayer Pins
        $taxpayer1 = TaxpayerPin::where('pin', 'P123456789Z')->first();
        $taxpayer2 = TaxpayerPin::where('pin', 'P987654321A')->first();

        // Assert that the relationships are created correctly
        $this->assertTrue($posClient->taxpayerPins()->where('pin', 'P123456789Z')->exists());
        $this->assertFalse($posClient->taxpayerPins()->where('pin', 'P987654321A')->exists());

        $this->assertTrue($erpClient->taxpayerPins()->where('pin', 'P987654321A')->exists());
        $this->assertFalse($erpClient->taxpayerPins()->where('pin', 'P123456789Z')->exists());

        $this->assertTrue($uiBackend->taxpayerPins()->where('pin', 'P123456789Z')->exists());
        $this->assertTrue($uiBackend->taxpayerPins()->where('pin', 'P987654321A')->exists());
    }
}
