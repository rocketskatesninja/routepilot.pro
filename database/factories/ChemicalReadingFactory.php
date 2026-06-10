<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChemicalReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChemicalReading>
 */
class ChemicalReadingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'free_chlorine' => 2.0,
            'ph' => 7.4,
            'alkalinity' => 100,
            'calcium_hardness' => 250,
        ];
    }
}
