<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Chat\AssistantTool;

/**
 * A homeowner's balance, as their Balance page shows it: what's owed and for
 * what, and recent payments. Paying happens on that page, never in the chat.
 */
class MyBalance extends AssistantTool
{
    public function name(): string
    {
        return 'my_balance';
    }

    public function description(): string
    {
        return 'What you owe and what for (visits and other charges), your recent payments, and whether autopay is on. '
            .'Paying is done on the Balance page.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass, 'required' => []];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'customer' && $this->tenant($user)?->customer_billing !== false;
    }

    public function execute(array $params, User $user): string
    {
        $customer = Customer::query()->where('tenant_id', (int) $user->tenant_id)->where('user_id', $user->id)->first();
        if ($customer === null) {
            return 'There is no customer account linked to this login.';
        }

        $b = app(BillingService::class)->breakdownForCustomer($customer);
        $payments = Payment::query()->where('customer_id', $customer->id)->where('status', 'succeeded')->latest('paid_at')->limit(3)->get();

        return 'You owe $'.number_format((float) $b['total'], 2).'.'
            .collect($b['visits'])->map(fn (array $v): string => "\n- {$v['service']} at {$v['pool']} on {$v['date']}: $".number_format((float) $v['price'], 2))->join('')
            .collect($b['charges'])->map(fn (array $c): string => "\n- {$c['description']}: $".number_format((float) $c['amount'], 2))->join('')
            ."\nAutopay is ".($customer->getAttribute('autopay_enabled') ? 'on' : 'off').'.'
            .($payments->isNotEmpty() ? "\nRecent payments: ".$payments->map(fn (Payment $p): string => '$'.number_format((float) $p->amount, 2).' on '.self::day($p->paid_at, 'M j'))->join(', ') : '');
    }
}
