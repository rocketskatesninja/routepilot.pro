<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** Office: find customers and see their contact details, pools and plans. */
class LookupCustomer extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_customer';
    }

    public function description(): string
    {
        return 'Find customers by name, address, city, zip, email or phone, and see their contact details, notes, pools '
            .'and plans (service, day, price, tech). Pass "all" to list every customer.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Part of a name, address, city, zip, email or phone, or "all".'],
            ],
            'required' => ['name'],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'tenant_admin';
    }

    public function execute(array $params, User $user): string
    {
        $search = trim((string) ($params['name'] ?? ''));
        $base = Customer::query()->where('customers.tenant_id', (int) $user->tenant_id);

        if (strtolower($search) === 'all') {
            $total = (clone $base)->count();
            $customers = $base->withCount('pools')->orderBy('last_name')->orderBy('first_name')->limit(self::ROWS)->get();

            return $customers->isEmpty() ? 'No customers yet.' : "Customers ({$total}):\n"
                .$customers->map(fn (Customer $c): string => '- '.self::link('customer', $c->id, $c->displayName())
                    .' — '.($this->address($c) ?: 'no address').' ('.$c->getAttribute('pools_count').' pool'.((int) $c->getAttribute('pools_count') === 1 ? '' : 's').')')->join("\n")
                .self::more($total, $customers->count());
        }

        if (($problem = $this->nameProblem($search)) !== null) {
            return $problem;
        }

        $like = '%'.$search.'%';
        $customers = $base
            ->where(function ($q) use ($search, $like) {
                $q->where(fn ($n) => $this->whereNameLike($n, $search))
                    ->orWhere('address_line1', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('zip', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->with(['pools.subscriptions' => fn ($s) => $s->whereIn('status', ['active', 'paused']), 'pools.subscriptions.serviceType', 'pools.subscriptions.agent'])
            ->limit(10)
            ->get();

        if ($customers->isEmpty()) {
            return "No customer found matching \"{$search}\".";
        }

        return $customers->map(function (Customer $c): string {
            $pools = $c->pools->map(fn (Pool $p): string => '  - '.self::link('pool', $p->id, $p->name)
                .($p->subscriptions->isEmpty() ? ': no active plan' : "\n".$p->subscriptions->map(fn (ServiceSubscription $s): string => '    - '.$this->planLine($s))->join("\n")))->join("\n");

            return collect([
                self::link('customer', $c->id, $c->displayName()),
                $c->email !== null ? "Email: {$c->email}" : 'No email on file',
                $c->phone !== null ? "Phone: {$c->phone}" : null,
                ($address = $this->address($c)) !== '' ? "Address: {$address}" : null,
                'Customer portal: '.$c->portalStatus(),
                ($note = self::note($c->notes)) !== null ? "Notes: {$note}" : null,
                $c->pools->isNotEmpty() ? "Pools:\n{$pools}" : 'No pools on file.',
            ])->filter()->join("\n");
        })->join("\n\n---\n\n");
    }

    private function address(Customer $c): string
    {
        return collect([$c->address_line1, $c->address_line2, $c->city, $c->state, $c->zip])->filter()->join(', ');
    }
}
