<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxpayerPinSeeder::class,
            ApiClientSeeder::class,
            KraDeviceSeeder::class,
            UserSeeder::class,
            TaxpayerPinUserSeeder::class,
            ApiClientTaxpayerPinSeeder::class,
        ]);
    }
}
