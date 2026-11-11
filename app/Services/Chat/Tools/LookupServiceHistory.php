<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\ChemicalTreatment;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Services\Chat\AssistantTool;

/** Office: a customer's or pool's past visits, with what was used and billed. */
class LookupServiceHistory extends AssistantTool
{
    public function name(): string
    {
        return 'lookup_service_history';
    }

    public function description(): string
    {
        return 'Look up past visits for a customer or pool: date, tech, status, chemicals and parts used, what was billed, '
            .'and the note to the homeowner. Each visit links to its report (office notes are only on the report).';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_name' => ['type' => 'string', 'description' => 'The customer\'s name (part of it is fine).'],
                'pool_name' => ['type' => 'string', 'description' => 'One pool\'s name (optional: leave out for all their pools).'],
                'pool_id' => ['type' => 'integer', 'description' => 'The pool\'s id, when you already know it (optional).'],
                'limit' => ['type' => 'integer', 'description' => 'How many recent visits (default 5, at most 20).'],
            ],
            'required' => [],
        ];
    }

    /** Office, and techs (their own visits unless Agent Plus, as on the Reports page). */
    public function availableTo(User $user): bool
    {
        return in_array($user->role, ['tenant_admin', 'agent'], true);
    }

    public function execute(array $params, User $user): string
    {
        $pools = $this->resolvePools($user, $params);
        if (is_string($pools)) {
            return $pools;
        }
        $limit = max(1, min(is_numeric($params['limit'] ?? null) ? (int) $params['limit'] : 5, 20));

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $pools->pluck('id'))
            ->when($this->ownVisitsOnly($user), fn ($q) => $q->where('agent_id', $user->id))
            ->with('pool', 'agent', 'chemicalReading', 'treatments.chemical:id,kind,part_number', 'charges')
            ->latest('visited_at')
            ->limit($limit)
            ->get();

        $who = $pools->first()?->customer?->displayName() ?? 'this customer';
        if ($visits->isEmpty()) {
            return "No visits yet for {$who}.";
        }

        $office = $user->role === 'tenant_admin';

        return "Recent visits for {$who}:\n".$visits->map(function (ServiceVisit $v) use ($office): string {
            [$parts, $chemicals] = $v->treatments->partition(fn (ChemicalTreatment $t): bool => $t->isPart());
            $extras = collect([
                $v->chemicalReading !== null ? 'water tested' : null,
                $chemicals->isNotEmpty() ? 'chemicals: '.$chemicals->map(fn (ChemicalTreatment $t): string => "{$t->chemical_name} {$t->amount} {$t->unit}")->join(', ') : null,
                $parts->isNotEmpty() ? 'parts: '.$parts->map(fn (ChemicalTreatment $t): string => $t->chemical_name.' ×'.(int) $t->amount)->join(', ') : null,
                $office ? $this->billed($v) : null,
            ])->filter()->join(' | ');

            return '- '.self::link('visit', $v->id, self::day($v->visited_at, 'M j, Y g:ia'))." — {$v->pool?->name} — "
                .($v->agent?->displayName() ?? 'unknown tech').' — '.$v->status
                .($extras !== '' ? "\n  {$extras}" : '')
                .(($n = self::note($v->notes)) !== null ? "\n  Notes: {$n}" : '');
        })->join("\n");
    }

    /** "billed $55.00", "billed $55.00 (voided)", or null if never billed. */
    private function billed(ServiceVisit $v): ?string
    {
        if ($v->charges->isEmpty()) {
            return null;
        }
        $active = $v->charges->whereNull('voided_at');
        $amount = ($active->isNotEmpty() ? $active : $v->charges)->sum(fn ($c): float => (float) $c->amount);

        return 'billed $'.number_format($amount, 2).($active->isEmpty() ? ' (voided)' : '');
    }
}
