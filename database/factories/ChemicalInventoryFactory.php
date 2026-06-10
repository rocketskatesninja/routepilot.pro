<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChemicalInventory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChemicalInventory>
 */
class ChemicalInventoryFactory extends Factory
{
    protected $model = ChemicalInventory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'chemical_name' => fake()->randomElement(['Liquid Chlorine', 'Muriatic Acid', 'Cal Hypo', 'Soda Ash', 'Cyanuric Acid', 'Algaecide']),
            'unit' => fake()->randomElement(['gal', 'lbs', 'oz']),
            'current_stock' => fake()->randomFloat(2, 5, 100),
            'reorder_threshold' => 10,
            'cost_per_unit' => fake()->randomFloat(2, 1, 8),
            'sell_price' => fake()->randomFloat(2, 3, 15),
            'is_active' => true,
        ];
    }

    /** Stock below the reorder threshold. */
    public function low(): static
    {
        return $this->state(fn (array $attributes) => ['current_stock' => 3, 'reorder_threshold' => 10]);
    }
}
