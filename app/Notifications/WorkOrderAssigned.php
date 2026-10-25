<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Someone else put a work order on your plate — in the bell, live.
 */
class WorkOrderAssigned extends Notification
{
    use Queueable;

    public function __construct(public WorkOrder $workOrder, public User $assignedBy) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('work_order_assigned') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $order = $this->workOrder;
        $where = $order->pool?->name;

        return [
            'title' => ($order->urgent ? 'Urgent work order for you: ' : 'Work order for you: ').$order->title,
            'body' => trim(($where !== null ? $where.' — ' : '').'assigned by '.$this->assignedBy->displayName()),
            'url' => '/work-orders?selected='.$order->id,
        ];
    }
}
