<?php
namespace Database\Seeders;
use App\Models\TaxpayerPin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxpayerPinSeeder extends Seeder
{
    public function run(): void
    {
        TaxpayerPin::firstOrCreate(
            ['pin' => 'P123456789Z'], // Unique identifier
            [
                'id' => (string) Str::uuid(),
                'name' => 'ICORE Test Business Ltd',
                'address' => '123 Test Avenue, Nairobi',
                'is_active' => true,
            ]
        );

        TaxpayerPin::firstOrCreate(
            ['pin' => 'P987654321A'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'KRA Demo Corp',
                'address' => '456 Sample Street, Mombasa',
                'is_active' => true,
            ]
        );
    }
}
