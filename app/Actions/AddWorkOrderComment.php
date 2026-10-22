<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;

/** Add a note to a work order's thread. */
class AddWorkOrderComment
{
    public function handle(WorkOrder $order, string $body, User $by): WorkOrderComment
    {
        $comment = new WorkOrderComment(['body' => trim($body)]);
        $comment->forceFill(['tenant_id' => $order->tenant_id, 'work_order_id' => $order->id, 'user_id' => $by->id])->save();

        return $comment;
    }
}
