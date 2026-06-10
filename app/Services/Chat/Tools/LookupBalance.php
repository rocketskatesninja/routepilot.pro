<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Services\BillingService;
use App\Services\Chat\AiTool;

/**
 * Check outstanding customer balances — one customer, or all who owe.
 */
class LookupBalance extends AiTool
{
    public function name(): string
    {
        return 'lookup_balance';
    }

    public function description(): string
    {
        return 'Check outstanding balances. Pass a customer_name for one customer, or omit it to list ALL '
            .'customers with balances (highest first). Use when asked about balances, unpaid amounts, who owes '
            .'money, or who owes the most.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'Customer name (fuzzy). Omit to list ALL customers with balances.'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $billing = app(BillingService::class);
        $name = trim((string) ($params['customer_name'] ?? ''));

        if ($name === '') {
            $balances = $billing->outstandingBalances();
            if ($balances->isEmpty()) {
                return 'No customers have an outstanding balance. 🎉';
            }
            $total = (float) $balances->sum(fn (array $r): float => $r['balance']);
            $rows = $balances->take(20)->map(
                fn (array $r): string => "- **{$r['customer']->displayName()}** [id:{$r['customer']->id}]: $".number_format($r['balance'], 2)
            )->join("\n");

            return 'Outstanding balances ('.$balances->count().' customers, $'.number_format($total, 2)." total):\n\n{$rows}";
        }

        $customers = Customer::query()->where(fn ($q) => $this->whereNameLike($q, $name))->get();
        if ($customers->isEmpty()) {
            return "No customer found matching \"{$name}\".";
        }

        return $customers->map(function (Customer $c) use ($billing): string {
            $balance = $billing->outstandingForCustomer($c);

            return "**{$c->displayName()}** [id:{$c->id}]: ".($balance > 0 ? '$'.number_format($balance, 2).' outstanding' : 'paid up ✓');
        })->join("\n");
    }
}
