<?php
namespace Database\Seeders;
use App\Models\ApiClient;
use App\Models\TaxpayerPin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str; // For Str::uuid and Str::random

class ApiClientSeeder extends Seeder
{
    public function run(): void
    {
        $taxpayer1 = TaxpayerPin::where('pin', 'P123456789Z')->first();
        $taxpayer2 = TaxpayerPin::where('pin', 'P987654321A')->first();

        // Client for POS integration (can access taxpayer1)
        $posClientApiKey = 'POS_API_KEY_EXAMPLE_1234567890ABCDEF'; // Plain text API Key for Postman
        ApiClient::firstOrCreate(
            ['name' => 'POS System Client'],
            [
                'id' => (string) Str::uuid(),
                'api_key' => $posClientApiKey, // Model will hash this
                'is_active' => true,
                'allowed_taxpayer_pins' => $taxpayer1 ? $taxpayer1->pin : '',
            ]
        );
        $this->command->info("POS Client API Key: " . $posClientApiKey);

        // Client for ERP integration (can access taxpayer2)
        $erpClientApiKey = 'ERP_API_KEY_EXAMPLE_FEDCBA0987654321'; // Plain text API Key for Postman
        ApiClient::firstOrCreate(
            ['name' => 'ERP System Client'],
            [
                'id' => (string) Str::uuid(),
                'api_key' => $erpClientApiKey, // Model will hash this
                'is_active' => true,
                'allowed_taxpayer_pins' => $taxpayer2 ? $taxpayer2->pin : '',
            ]
        );
         $this->command->info("ERP Client API Key: " . $erpClientApiKey);

        // Client for UI Backend (can access all taxpayers)
        $uiBackendApiKey = 'UI_BACKEND_API_KEY_SECURE_ABC123XYZ'; // Plain text API Key for .env
        $allTaxpayerPins = TaxpayerPin::pluck('pin')->implode(',');
        ApiClient::firstOrCreate(
            ['name' => 'ICORE_UI_BACKEND'],
            [
                'id' => (string) Str::uuid(),
                'api_key' => $uiBackendApiKey, // Model will hash this
                'is_active' => true,
                'allowed_taxpayer_pins' => $allTaxpayerPins,
            ]
        );
        $this->command->info("UI Backend API Key: " . $uiBackendApiKey);
    }
}