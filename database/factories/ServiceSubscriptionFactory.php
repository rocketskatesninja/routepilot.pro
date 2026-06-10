<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSubscription>
 */
class ServiceSubscriptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'frequency' => 'weekly',
            'preferred_day' => 'tuesday',
            'status' => 'active',
        ];
    }
}
