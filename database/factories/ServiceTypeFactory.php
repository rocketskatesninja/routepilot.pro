<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Service',
            'frequency' => 'weekly',
            'estimated_duration_minutes' => 30,
            'price' => 50,
            'is_active' => true,
        ];
    }
}
