<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Pool;

/**
 * Update a pool's specs and its service location (upserted). The owning
 * customer is not reassigned here.
 */
class UpdatePool
{
    /**
     * @param  array<string, mixed>  $data  validated pool + location fields
     */
    public function handle(Pool $pool, array $data): Pool
    {
        $pool->update([
            'name' => $data['name'],
            'type' => $data['type'] ?? 'inground',
            'volume_gallons' => $data['volume_gallons'] ?? null,
            'surface_type' => $data['surface_type'] ?? null,
            'sanitizer_type' => $data['sanitizer_type'] ?? 'chlorine',
            'filter_type' => $data['filter_type'] ?? null,
            'pump_type' => $data['pump_type'] ?? null,
            'has_heater' => $data['has_heater'] ?? false,
            'has_automation' => $data['has_automation'] ?? false,
            'has_pool_cleaner' => $data['has_pool_cleaner'] ?? false,
            'has_cover' => $data['has_cover'] ?? false,
            'has_water_feature' => $data['has_water_feature'] ?? false,
            'has_auto_fill' => $data['has_auto_fill'] ?? false,
            'notes' => $data['notes'] ?? null,
        ]);

        $pool->serviceLocation()->updateOrCreate([], [
            'address_line1' => $data['address_line1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'gate_code' => $data['gate_code'] ?? null,
            'access_notes' => $data['access_notes'] ?? null,
        ]);

        return $pool;
    }
}
