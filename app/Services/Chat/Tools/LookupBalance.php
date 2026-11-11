<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Chat\AssistantTool;

/** Office, when the company bills through RoutePilot: who owes what, and their open invoices. */
class LookupBalance extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_balance';
    }

    public function description(): string
    {
        return 'Check what customers owe. With a customer name: their balance and unpaid invoices. Without: everyone who '
            .'owes money, highest first.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'One customer (optional: leave out to list everyone who owes).'],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'tenant_admin' && $this->tenant($user)?->customer_billing !== false;
    }

    public function execute(array $params, User $user): string
    {
        $billing = app(BillingService::class);
        $name = trim((string) ($params['customer_name'] ?? ''));

        if ($name === '') {
            $balances = $billing->outstandingBalances();
            if ($balances->isEmpty()) {
                return 'Nobody owes anything right now.';
            }
            $total = (float) $balances->sum(fn (array $r): float => $r['balance']);
            $shown = $balances->take(self::ROWS);

            return 'Owed: $'.number_format($total, 2).' across '.$balances->count()." customers:\n"
                .$shown->map(fn (array $r): string => '- '.self::link('balance', $r['customer']->id, $r['customer']->displayName()).': $'.number_format($r['balance'], 2))->join("\n")
                .self::more($balances->count(), $shown->count());
        }

        if (($problem = $this->nameProblem($name)) !== null) {
            return $problem;
        }
        $customers = Customer::query()->where('tenant_id', (int) $user->tenant_id)->where(fn ($q) => $this->whereNameLike($q, $name))->limit(10)->get();
        if ($customers->isEmpty()) {
            return "No customer found matching \"{$name}\".";
        }

        return $customers->map(function (Customer $c) use ($billing): string {
            $owed = $billing->outstandingForCustomer($c);
            $open = Invoice::query()->where('customer_id', $c->id)->whereNotIn('status', ['draft', 'paid', 'void'])->latest('issued_at')->limit(10)->get()
                ->filter(fn (Invoice $i): bool => $i->balance() > 0);

            return self::link('balance', $c->id, $c->displayName()).': '.($owed > 0 ? '$'.number_format($owed, 2).' owed' : 'paid up')
                .$open->map(fn (Invoice $i): string => "\n  - Invoice {$i->number}: $".number_format($i->balance(), 2).' of $'.number_format((float) $i->total, 2)
                    .", {$i->status}".($i->due_at !== null ? ', due '.$i->due_at->format('M j') : ''))->join('');
        })->join("\n");
    }
}
