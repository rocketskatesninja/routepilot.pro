<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Customer;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\Chat\AssistantTool;
use App\Support\PortalVisit;

/** A homeowner's finished visits, exactly as their Service History shows them (never office notes). */
class MyVisits extends AssistantTool
{
    public function name(): string
    {
        return 'my_visits';
    }

    public function description(): string
    {
        return 'Your recent visits: the date, pool, service, technician, water test, chemicals added, parts replaced, and '
            .'the note your technician left. Each links to its report.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'How many recent visits (default 5, at most 15).'],
            ],
            'required' => [],
        ];
    }

    public function availableTo(User $user): bool
    {
        return $user->role === 'customer';
    }

    public function execute(array $params, User $user): string
    {
        $customer = Customer::query()->where('tenant_id', (int) $user->tenant_id)->where('user_id', $user->id)->first();
        if ($customer === null) {
            return 'There is no customer account linked to this login.';
        }
        $limit = max(1, min(is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 5, 15));

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $customer->pools()->pluck('id'))
            ->where('status', 'completed')
            ->with(PortalVisit::WITH)
            ->latest('completed_at')
            ->limit($limit)
            ->get();

        if ($visits->isEmpty()) {
            return 'No visits yet.';
        }

        return "Your recent visits:\n".$visits->map(function (ServiceVisit $v): string {
            $a = PortalVisit::toArray($v);
            $reading = is_array($a['reading'] ?? null) ? collect($a['reading'])->filter(fn ($x): bool => $x !== null)->map(fn ($x, $k): string => str_replace('_', ' ', (string) $k)." {$x}")->join(', ') : '';
            /** @var list<array{name: string, amount: float, unit?: string}> $treatments */
            $treatments = is_array($a['treatments'] ?? null) ? $a['treatments'] : [];
            /** @var list<array{name: string, amount: float}> $used */
            $used = is_array($a['parts'] ?? null) ? $a['parts'] : [];
            $treat = collect($treatments)->map(fn (array $t): string => trim("{$t['name']} {$t['amount']} ".($t['unit'] ?? '')))->join(', ');
            $parts = collect($used)->map(fn (array $t): string => "{$t['name']} ×{$t['amount']}")->join(', ');

            return '- '.self::link('visit', $v->id, (string) ($a['on'] ?? 'visit')).' — '.($a['pool'] ?? 'pool')
                .(isset($a['service']) ? " — {$a['service']}" : '').' — '.($a['agent'] ?? 'your technician')
                .($reading !== '' ? "\n  Water test: {$reading}" : '')
                .($treat !== '' ? "\n  Chemicals added: {$treat}" : '')
                .($parts !== '' ? "\n  Parts replaced: {$parts}" : '')
                .(($n = self::note(is_string($a['notes'] ?? null) ? $a['notes'] : null)) !== null ? "\n  Technician's note: {$n}" : '');
        })->join("\n");
    }
}
