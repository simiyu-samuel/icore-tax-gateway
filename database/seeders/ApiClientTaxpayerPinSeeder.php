<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use App\Models\TaxpayerPin;
use Illuminate\Database\Seeder;

class ApiClientTaxpayerPinSeeder extends Seeder
{
    public function run(): void
    {
        $posClient = ApiClient::where('name', 'POS System Client')->first();
        $erpClient = ApiClient::where('name', 'ERP System Client')->first();
        $uiBackend = ApiClient::where('name', 'ICORE_UI_BACKEND')->first();

        $taxpayer1 = TaxpayerPin::where('pin', 'P123456789Z')->first();
        $taxpayer2 = TaxpayerPin::where('pin', 'P987654321A')->first();

        if ($posClient && $taxpayer1) {
            $posClient->taxpayerPins()->sync([$taxpayer1->id]);
        }

        if ($erpClient && $taxpayer2) {
            $erpClient->taxpayerPins()->sync([$taxpayer2->id]);
        }

        if ($uiBackend) {
            $taxpayerPins = TaxpayerPin::pluck('id')->toArray();
            $uiBackend->taxpayerPins()->sync($taxpayerPins);
        }
    }
}
