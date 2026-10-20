<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A tech raised a work order from a visit — tells the office (admins and Agent+)
 * in the bell, live.
 */
class WorkOrderRaised extends Notification
{
    use Queueable;

    public function __construct(public WorkOrder $workOrder, public User $raisedBy) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('work_orders') ? [] : ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $order = $this->workOrder;
        $where = $order->pool?->name;

        return [
            'title' => ($order->urgent ? 'Urgent work order: ' : 'Work order: ').$order->title,
            'body' => trim(($where !== null ? $where.' — ' : '').'raised by '.$this->raisedBy->displayName()),
            // The visit's report shows it until the work orders list exists.
            'url' => '/reports?selected='.$order->service_visit_id,
        ];
    }
}
