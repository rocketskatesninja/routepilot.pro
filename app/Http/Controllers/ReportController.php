<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Reports screen — completed service visits, read-only, on the
 * table+drawer pattern. The drawer is the full visit report: readings,
 * treatments, tasks, and notes.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $query = ServiceVisit::query()
            ->where('status', 'completed')
            ->with(['pool:id,name,customer_id,photo_path', 'pool.customer:id,first_name,last_name,photo_path', 'agent:id,first_name,last_name,avatar_path']);

        // Relation columns sort via correlated subqueries — no joins, so the
        // tenant scope's `tenant_id` stays unambiguous.
        $sort = $this->applySort($query, $request, [
            'date' => 'completed_at',
            'pool' => fn ($q, $dir) => $q->orderBy(Pool::query()->select('name')->whereColumn('pools.id', 'service_visits.pool_id'), $dir),
            'customer' => fn ($q, $dir) => $q->orderBy(
                Customer::query()->select('first_name')
                    ->whereIn('customers.id', Pool::query()->select('customer_id')->whereColumn('pools.id', 'service_visits.pool_id'))
                    ->limit(1),
                $dir,
            ),
            'agent' => fn ($q, $dir) => $q->orderBy(User::query()->select('first_name')->whereColumn('users.id', 'service_visits.agent_id'), $dir),
        ], 'date', 'desc');

        $visits = $query
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ServiceVisit $v) => [
                'id' => $v->id,
                'completed_on' => $v->completed_at?->toDateString(),
                'pool' => $v->pool?->name,
                'pool_photo' => $this->photoUrl($v->pool?->getAttribute('photo_path')),
                'customer' => $v->pool?->customer?->displayName(),
                'customer_photo' => $this->photoUrl($v->pool?->customer?->getAttribute('photo_path')),
                'agent' => $v->agent?->displayName(),
                'agent_photo' => $this->photoUrl($v->agent?->getAttribute('avatar_path')),
            ]);

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $visit = ServiceVisit::query()
                ->with(['pool:id,name,customer_id', 'pool.customer:id,first_name,last_name', 'agent:id,first_name,last_name', 'chemicalReading', 'treatments', 'tasks'])
                ->find($selectedId);
            if ($visit !== null) {
                $selected = $this->toDetail($visit);
            }
        }

        return Inertia::render('reports/Index', [
            'visits' => $visits,
            'selected' => $selected,
            'sort' => $sort,
        ]);
    }

    /** @return array<string, mixed> */
    private function toDetail(ServiceVisit $visit): array
    {
        $reading = $visit->chemicalReading;

        return [
            'id' => $visit->id,
            'pool' => $visit->pool?->name,
            'customer' => $visit->pool?->customer?->displayName(),
            'customer_id' => $visit->pool?->customer?->getKey(),
            'agent' => $visit->agent?->displayName(),
            'agent_id' => $visit->agent?->getKey(),
            'completed_on' => $visit->completed_at?->toDateString(),
            'notes' => $visit->notes,
            'reading' => $reading !== null ? [
                'free_chlorine' => $reading->free_chlorine,
                'total_chlorine' => $reading->total_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'calcium_hardness' => $reading->calcium_hardness,
                'cyanuric_acid' => $reading->cyanuric_acid,
                'salt' => $reading->salt,
                'water_temperature' => $reading->water_temperature,
                'lsi_score' => $reading->lsi_score,
            ] : null,
            'treatments' => $visit->treatments->map(fn ($t) => [
                'name' => $t->getAttribute('chemical_name'),
                'amount' => (float) $t->getAttribute('amount'),
                'unit' => $t->getAttribute('unit'),
            ])->all(),
            'tasks' => $visit->tasks->map(fn ($t) => [
                'name' => $t->getAttribute('task_name'),
                'done' => (bool) $t->getAttribute('is_completed'),
            ])->all(),
        ];
    }
}
