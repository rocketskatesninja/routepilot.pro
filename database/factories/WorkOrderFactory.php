<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => 'Replace the skimmer lid',
            'status' => 'open',
            'urgent' => false,
        ];
    }
}
