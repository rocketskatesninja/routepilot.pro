<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PoolEquipment;

class CreateEquipment
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): PoolEquipment
    {
        return PoolEquipment::create([
            'pool_id' => $data['pool_id'],
            'type' => $data['type'],
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'serial' => $data['serial'] ?? null,
            'installed_on' => $data['installed_on'] ?? null,
            'warranty_until' => $data['warranty_until'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
