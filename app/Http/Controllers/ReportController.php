<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ServiceVisit;
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

        $visits = ServiceVisit::query()
            ->where('status', 'completed')
            ->with(['pool:id,name,customer_id,photo_path', 'pool.customer:id,first_name,last_name,photo_path', 'agent:id,first_name,last_name,avatar_path'])
            ->latest('completed_at')
            ->paginate(20)
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
            'agent' => $visit->agent?->displayName(),
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
