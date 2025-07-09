<?php
namespace Database\Seeders;
use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KraDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $taxpayer1 = TaxpayerPin::where('pin', 'P123456789Z')->first();
        $taxpayer2 = TaxpayerPin::where('pin', 'P987654321A')->first();

        if ($taxpayer1) {
            KraDevice::firstOrCreate(
                ['kra_scu_id' => 'KRA-TEST-OSCU-001'],
                [
                    'id' => (string) Str::uuid(),
                    'taxpayer_pin_id' => $taxpayer1->id,
                    'device_type' => 'OSCU',
                    'status' => 'ACTIVATED',
                    'config' => ['branch_office_id' => '00']
                ]
            );
            KraDevice::firstOrCreate(
                ['kra_scu_id' => 'KRA-TEST-VSCU-001'],
                [
                    'id' => (string) Str::uuid(),
                    'taxpayer_pin_id' => $taxpayer1->id,
                    'device_type' => 'VSCU',
                    'status' => 'ACTIVATED',
                    'config' => [
                        'branch_office_id' => '00',
                        'vscu_jar_url' => config('kra.vscu_jar_base_url') // Use configured local URL
                    ]
                ]
            );
        }

        if ($taxpayer2) {
             KraDevice::firstOrCreate(
                ['kra_scu_id' => 'KRA-TEST-OSCU-002'],
                [
                    'id' => (string) Str::uuid(),
                    'taxpayer_pin_id' => $taxpayer2->id,
                    'device_type' => 'OSCU',
                    'status' => 'ACTIVATED',
                    'config' => ['branch_office_id' => '01']
                ]
            );
        }
    }
}