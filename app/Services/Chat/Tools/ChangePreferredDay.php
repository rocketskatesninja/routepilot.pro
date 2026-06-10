<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ServiceSubscription;
use App\Services\Chat\AiTool;

/**
 * Change the preferred service day for a customer's pool subscription.
 */
class ChangePreferredDay extends AiTool
{
    public function name(): string
    {
        return 'change_preferred_day';
    }

    public function description(): string
    {
        return 'Change the preferred service day for a customer\'s pool subscription. '
            .'Use this when the tenant asks to move a customer\'s service to a different day of the week.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'The customer\'s name'],
                'pool_name' => ['type' => 'string', 'description' => 'The pool name (e.g. "Main Pool", "Lap Pool")'],
                'new_day' => [
                    'type' => 'string',
                    'enum' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                    'description' => 'The new preferred day',
                ],
            ],
            'required' => ['customer_name', 'new_day'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $customerName = trim((string) ($params['customer_name'] ?? ''));
        $poolName = trim((string) ($params['pool_name'] ?? ''));
        $newDay = strtolower(trim((string) ($params['new_day'] ?? '')));

        $subs = ServiceSubscription::query()
            ->where('status', 'active')
            ->whereHas('pool.customer', fn ($q) => $this->whereNameLike($q, $customerName))
            ->when($poolName !== '', fn ($q) => $q->whereHas('pool', fn ($p) => $p->where('name', 'like', "%{$poolName}%")))
            ->with('pool.customer', 'serviceType')
            ->get();

        if ($subs->isEmpty()) {
            return "No active subscription found for \"{$customerName}\".";
        }
        if ($subs->count() > 1 && $poolName === '') {
            $list = $subs->map(fn (ServiceSubscription $s): string => "- {$s->pool?->name}: {$s->serviceType->name}")->join("\n");

            return "Multiple subscriptions found. Please specify the pool:\n{$list}";
        }

        $sub = $subs->first();
        $oldDay = $sub->preferred_day;
        $sub->update(['preferred_day' => $newDay]);

        return "Done! Moved {$sub->pool?->customer?->displayName()}'s \"{$sub->pool?->name}\" from "
            .ucfirst($oldDay ?? 'unset').' to '.ucfirst($newDay).'.';
    }
}
