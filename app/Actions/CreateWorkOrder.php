<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderAssigned;
use App\Notifications\WorkOrderRaised;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Add a work order to the company's task list. One raised from a visit tells
 * the office (admins and Agent+), and whoever it's assigned to hears it's
 * theirs, once whatever wraps this has saved, so a visit that fails to save
 * never sends an alert for a task that doesn't exist.
 */
class CreateWorkOrder
{
    /** @param array{title: string, details?: string|null, urgent?: bool, due_on?: string|null, price?: float|string|null, assigned_to?: int|null, pool_id?: int|null, service_visit_id?: int|null} $data */
    public function handle(array $data, User $by): WorkOrder
    {
        $order = new WorkOrder($data);
        $order->forceFill(['tenant_id' => $by->tenant_id, 'created_by' => $by->id, 'status' => 'open'])->save();

        if ($order->service_visit_id !== null) {
            DB::afterCommit(fn () => $this->tellTheOffice($order, $by));
        }
        DB::afterCommit(fn () => self::tellTheAssignee($order, $by));

        return $order;
    }

    /**
     * What a tech typed, as a title (its first line) and the details (the lines
     * after it). A first line too long for a title is cut to fit, and then the
     * details keep the whole text so nothing is lost.
     *
     * @return array{title: string, details: string|null}
     */
    public static function fromText(string $text): array
    {
        $text = trim($text);
        $first = trim(Str::before($text, "\n"));
        $title = Str::limit($first, 157);
        $details = $title === $first ? trim(Str::after($text, $first)) : $text;

        return ['title' => $title, 'details' => $details === '' ? null : $details];
    }

    /** Tell the person it's assigned to, unless they assigned it to themselves. */
    public static function tellTheAssignee(WorkOrder $order, User $by): void
    {
        if ($order->assigned_to === null || $order->assigned_to === $by->id) {
            return;
        }
        $assignee = User::query()->where('tenant_id', $order->tenant_id)->where('is_active', true)->find($order->assigned_to);
        $assignee?->notify(new WorkOrderAssigned($order, $by));
    }

    private function tellTheOffice(WorkOrder $order, User $by): void
    {
        $office = User::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('role', 'tenant_admin')->orWhere(fn ($q) => $q->where('role', 'agent')->where('agent_plus', true)))
            ->whereKeyNot($by->id)
            ->get();

        Notification::send($office, new WorkOrderRaised($order, $by));
    }
}
