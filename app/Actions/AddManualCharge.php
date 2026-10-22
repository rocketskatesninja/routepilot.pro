<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Charge;
use App\Models\Customer;
use App\Models\WorkOrder;
use App\Services\BillingService;

/**
 * Add a one-off charge to a customer's account (raises their balance). One that
 * bills a work order remembers it, and its pool.
 */
class AddManualCharge
{
    public function __construct(private BillingService $billing) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $userId): Charge
    {
        $order = isset($data['work_order_id']) ? WorkOrder::query()->findOrFail($data['work_order_id']) : null;

        return $this->billing->addCharge(
            Customer::query()->findOrFail($data['customer_id']),
            'manual',
            (string) $data['description'],
            (float) $data['amount'],
            (bool) ($data['taxable'] ?? true),
            $data['occurred_on'] ?? null,
            $userId,
            $order?->pool_id,
            $order?->id,
        );
    }
}
