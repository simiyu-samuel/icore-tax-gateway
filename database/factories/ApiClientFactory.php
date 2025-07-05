<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiClient>
 */
class ApiClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ApiClient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'api_key' => Hash::make($this->faker->regexify('[A-Z0-9]{32}')),
            'is_active' => true,
            'allowed_taxpayer_pins' => 'A123456789B,A987654321C',
            'last_used_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
} 