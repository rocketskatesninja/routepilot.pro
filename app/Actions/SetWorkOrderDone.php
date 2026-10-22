<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\WorkOrder;

/** Mark a work order done (recording who and when), or reopen it. */
class SetWorkOrderDone
{
    public function handle(WorkOrder $order, User $by, bool $done): void
    {
        $order->forceFill($done
            ? ['status' => 'done', 'completed_by' => $by->id, 'completed_at' => now()]
            : ['status' => 'open', 'completed_by' => null, 'completed_at' => null])->save();
    }
}
