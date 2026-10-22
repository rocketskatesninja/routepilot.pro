<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\WorkOrder;

/**
 * Edit a work order: what it is, where, who has it, when it's due, how urgent.
 * Handing it to someone new tells them.
 */
class UpdateWorkOrder
{
    /** @param array{title: string, details?: string|null, urgent?: bool, due_on?: string|null, price?: float|string|null, assigned_to?: int|null, pool_id?: int|null} $data */
    public function handle(WorkOrder $order, array $data, User $by): void
    {
        $order->fill($data);
        $reassigned = $order->isDirty('assigned_to');
        $order->save();

        if ($reassigned) {
            CreateWorkOrder::tellTheAssignee($order, $by);
        }
    }
}
