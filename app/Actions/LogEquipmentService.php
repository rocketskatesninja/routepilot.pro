<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\EquipmentServiceLog;
use App\Models\ManualCharge;
use App\Models\PoolEquipment;
use Illuminate\Support\Facades\DB;

/**
 * Log a repair/service against equipment, optionally billing its cost to the
 * pool's customer as a manual charge.
 */
class LogEquipmentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PoolEquipment $equipment, array $data, int $userId): EquipmentServiceLog
    {
        return DB::transaction(function () use ($equipment, $data, $userId): EquipmentServiceLog {
            $cost = (float) ($data['cost'] ?? 0);
            $servicedOn = $data['serviced_on'] ?? now()->toDateString();

            $log = $equipment->serviceLog()->create([
                'serviced_on' => $servicedOn,
                'description' => $data['description'],
                'cost' => $cost,
                'created_by' => $userId,
            ]);

            $customerId = $equipment->pool?->getAttribute('customer_id');
            if (! empty($data['bill']) && $cost > 0 && $customerId !== null) {
                ManualCharge::create([
                    'customer_id' => $customerId,
                    'description' => 'Repair: '.ucfirst((string) $equipment->type).' — '.$data['description'],
                    'amount' => $cost,
                    'taxable' => true,
                    'occurred_on' => $servicedOn,
                    'created_by' => $userId,
                ]);
            }

            return $log;
        });
    }
}
