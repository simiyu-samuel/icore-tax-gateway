<?php

namespace Database\Factories;

use App\Models\TaxpayerPin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxpayerPin>
 */
class TaxpayerPinFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TaxpayerPin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pin' => 'A' . $this->faker->numberBetween(100000000, 999999999) . 'B',
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'is_active' => true,
        ];
    }
} 