<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Models\Pool;
use App\Services\Chat\AiTool;

/**
 * Find customer details by name or address — contact info, address, notes,
 * and their pools.
 */
class LookupCustomer extends AiTool
{
    public function name(): string
    {
        return 'lookup_customer';
    }

    public function description(): string
    {
        return 'Look up customer details — contact info, address, notes, and pools. '
            .'Pass a name to search for a specific customer, or pass "all" to list ALL customers with addresses. '
            .'Also searches by address (e.g. "Oak Lane"). '
            .'Use when asked about a customer\'s info, who lives somewhere, or to compare customer locations.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Customer name (fuzzy match), partial address, or "all" to list every customer with their address',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $search = trim((string) ($params['name'] ?? ''));

        if (strtolower($search) === 'all') {
            $customers = Customer::query()->with('pools')->get();
            if ($customers->isEmpty()) {
                return 'No customers on file.';
            }

            return "All customers ({$customers->count()}):\n\n".$customers->map(function (Customer $c): string {
                $address = collect([$c->address_line1, $c->city, $c->state, $c->zip])->filter()->join(', ');
                $poolCount = $c->pools->count();

                return "- **{$c->displayName()}** [id:{$c->id}] — ".($address !== '' ? $address : 'No address')
                    ." ({$poolCount} pool".($poolCount !== 1 ? 's' : '').')';
            })->join("\n");
        }

        $customers = Customer::query()
            ->where(function ($q) use ($search) {
                $this->whereNameLike($q, $search);
                $q->orWhere('address_line1', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('zip', 'like', "%{$search}%");
            })
            ->with('pools')
            ->limit(10)
            ->get();

        if ($customers->isEmpty()) {
            return "No customer found matching \"{$search}\".";
        }

        return $customers->map(function (Customer $c): string {
            $address = collect([$c->address_line1, $c->address_line2, $c->city, $c->state, $c->zip])->filter()->join(', ');
            $pools = $c->pools->map(function (Pool $p): string {
                $vol = $p->volume_gallons !== null ? number_format($p->volume_gallons).' gal' : 'unknown volume';

                return "  - {$p->name} [id:{$p->id}] ({$p->type}, {$vol})";
            })->join("\n");

            return collect([
                "**{$c->displayName()}** [id:{$c->id}]",
                $c->email !== null ? "Email: {$c->email}" : null,
                $c->phone !== null ? "Phone: {$c->phone}" : null,
                $address !== '' ? "Address: {$address}" : null,
                $c->notes !== null ? "Notes: {$c->notes}" : null,
                $c->pools->isNotEmpty() ? "Pools:\n{$pools}" : 'No pools on file.',
            ])->filter()->join("\n");
        })->join("\n\n---\n\n");
    }
}
