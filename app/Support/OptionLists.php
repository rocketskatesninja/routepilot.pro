<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\User;

/**
 * Shared select-option lists for assignment forms (customer / service-type /
 * agent pickers). One definition so the pool and balance editors stay in sync.
 */
final class OptionLists
{
    /** @return array<int, array{id: int, name: string}> */
    public static function customers(): array
    {
        return Customer::query()
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Customer $c): array => ['id' => $c->id, 'name' => $c->displayName()])
            ->all();
    }

    /** @return array<int, array{id: int, name: string}> */
    public static function serviceTypes(): array
    {
        return ServiceType::query()
            ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (ServiceType $t): array => ['id' => $t->id, 'name' => $t->name])
            ->all();
    }

    /** @return array<int, array{id: int, name: string}> */
    public static function agents(): array
    {
        // Includes tenant_admins so a one-person operation can assign routes to themselves.
        return User::query()
            ->where('tenant_id', app('tenant_id'))->whereIn('role', ['agent', 'tenant_admin'])->where('is_active', true)
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'role'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->role === 'tenant_admin' ? $u->displayName().' (admin)' : $u->displayName(),
            ])
            ->all();
    }
}
