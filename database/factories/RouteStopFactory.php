<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'stop_order' => 1,
            'status' => 'pending',
        ];
    }
}
