<?php

namespace Database\Factories;

use App\Models\KraDevice;
use App\Models\TaxpayerPin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KraDevice>
 */
class KraDeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = KraDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taxpayer_pin_id' => TaxpayerPin::factory(),
            'kra_scu_id' => 'KRACU' . $this->faker->numberBetween(100000000, 999999999),
            'device_type' => $this->faker->randomElement(['OSCU', 'VSCU']),
            'status' => $this->faker->randomElement(['PENDING', 'ACTIVATED', 'UNAVAILABLE', 'ERROR']),
            'config' => null,
            'last_status_check_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
} 