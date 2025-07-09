<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TaxpayerPinSeeder::class,
            KraDeviceSeeder::class,
            ApiClientSeeder::class, // Run after TaxpayerPinSeeder
            UserSeeder::class,
            TaxpayerPinUserSeeder::class, // Run after UserSeeder and TaxpayerPinSeeder
        ]);
    }
}