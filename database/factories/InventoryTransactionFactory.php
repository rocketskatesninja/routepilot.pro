<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryTransaction;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => 'usage',
            'quantity' => fake()->randomFloat(2, 0.5, 5),
        ];
    }
}
