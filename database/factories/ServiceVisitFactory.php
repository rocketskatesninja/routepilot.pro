<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceVisit>
 */
class ServiceVisitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'status' => 'completed',
            'visited_at' => now()->subHour(),
            'completed_at' => now(),
        ];
    }
}
