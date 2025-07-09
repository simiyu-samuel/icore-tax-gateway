<?php
namespace Database\Seeders;
use App\Models\User;
use App\Models\TaxpayerPin;
use Illuminate\Database\Seeder;

class TaxpayerPinUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@icore.com')->first();
        $taxpayerUser1 = User::where('email', 'taxpayer1@example.com')->first();

        $taxpayer1 = TaxpayerPin::where('pin', 'P123456789Z')->first();
        $taxpayer2 = TaxpayerPin::where('pin', 'P987654321A')->first();

        if ($adminUser) {
            // Admin can manage all taxpayers
            $adminUser->taxpayerPins()->syncWithoutDetaching([$taxpayer1->id, $taxpayer2->id]);
        }

        if ($taxpayerUser1 && $taxpayer1) {
            // Taxpayer user 1 can only manage taxpayer 1
            $taxpayerUser1->taxpayerPins()->syncWithoutDetaching([$taxpayer1->id]);
        }
    }
}