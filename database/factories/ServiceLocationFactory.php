<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceLocation>
 */
class ServiceLocationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'FL',
            'zip' => fake()->postcode(),
        ];
    }
}
