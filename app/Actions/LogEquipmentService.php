<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\EquipmentServiceLog;
use App\Models\PoolEquipment;
use App\Models\Tenant;
use App\Services\BillingService;
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
            $servicedOn = $data['serviced_on'] ?? Tenant::localToday()->toDateString();

            $log = $equipment->serviceLog()->create([
                'serviced_on' => $servicedOn,
                'description' => $data['description'],
                'cost' => $cost,
                'created_by' => $userId,
            ]);

            $customerId = $equipment->pool?->getAttribute('customer_id');
            if (! empty($data['bill']) && $cost > 0 && $customerId !== null) {
                app(BillingService::class)->addCharge(
                    Customer::query()->findOrFail($customerId),
                    'manual',
                    'Repair: '.ucfirst((string) $equipment->type).' — '.$data['description'],
                    $cost,
                    true,
                    $servicedOn,
                    $userId,
                    $equipment->pool->id,
                );
            }

            return $log;
        });
    }
}
