<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pool>
 */
class PoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'customer_id' => Customer::factory()->for($tenant),
            'name' => fake()->word().' Pool',
            'type' => 'inground',
            'volume_gallons' => fake()->numberBetween(10000, 30000),
            'sanitizer_type' => 'chlorine',
        ];
    }
}
