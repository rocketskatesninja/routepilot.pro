<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

/**
 * Who may do what with the company's work orders. Office admins and Agent+ run
 * the whole list; a regular tech sees, notes and closes only what's assigned to
 * them (they raise new ones from their visits). Only admins delete.
 */
class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true);
    }

    public function view(User $user, WorkOrder $order): bool
    {
        return $this->ours($user, $order) && ($this->runsTheList($user) || $order->assigned_to === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $this->runsTheList($user);
    }

    public function update(User $user, WorkOrder $order): bool
    {
        return $this->ours($user, $order) && $this->runsTheList($user);
    }

    /** Mark done or reopen. */
    public function complete(User $user, WorkOrder $order): bool
    {
        return $this->view($user, $order);
    }

    public function comment(User $user, WorkOrder $order): bool
    {
        return $this->view($user, $order);
    }

    public function delete(User $user, WorkOrder $order): bool
    {
        return $this->ours($user, $order) && $user->role === 'tenant_admin';
    }

    /**
     * Putting it on its customer's balance: admins (who add charges), once it's done, when
     * it's on a customer's pool, and only once (unless that charge was cancelled).
     */
    public function bill(User $user, WorkOrder $order): bool
    {
        return $this->ours($user, $order) && $user->role === 'tenant_admin' && $order->status === 'done'
            && $order->pool?->customer !== null && $order->billedCharge() === null;
    }

    /** Admins and Agent+ see and work every work order. */
    public static function runsTheList(User $user): bool
    {
        return $user->role === 'tenant_admin' || $user->isAgentPlus();
    }

    private function ours(User $user, WorkOrder $order): bool
    {
        return $user->tenant_id !== null && $order->tenant_id === $user->tenant_id;
    }
}
