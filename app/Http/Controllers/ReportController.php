<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteReport;
use App\Actions\UpdateReport;
use App\Http\Requests\UpdateReportRequest;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Reports screen — completed service visits on the table+drawer
 * pattern. The drawer is the full visit report: readings, treatments, tasks,
 * and notes. The office and Agent+ agents see every report; other agents see
 * only their own. Admins edit or delete any report, agents edit their own.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);
        $user = $request->user();
        $ownOnly = $user?->role === 'agent' && ! $user->isAgentPlus();
        if ($user?->role === 'tenant_admin') {
            NavCounts::sawReports($user); // clears the sidebar's Reports number
        }

        $query = ServiceVisit::query()
            ->where('status', 'completed')
            ->when($ownOnly, fn ($q) => $q->where('agent_id', $user?->id))
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
        // Visits that tie (same pool, customer or agent) read newest first.
        $query->orderByDesc('completed_at')->orderByDesc('id');

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
                ->when($ownOnly, fn ($q) => $q->where('agent_id', $user?->id))
                ->with([
                    'pool:id,name,customer_id,custom_target_ranges', 'pool.customer:id,first_name,last_name', 'agent:id,first_name,last_name',
                    'serviceSubscription.serviceType:id,name', 'chemicalReading', 'treatments', 'tasks', 'photos',
                    'workOrder:id,service_visit_id,title,details,urgent,status',
                ])
                ->find($selectedId);
            if ($visit !== null) {
                $selected = $this->toDetail($visit);
                // Admins may edit any report; an agent only their own. Only admins delete.
                $selected['can_edit'] = $this->canManage($request->user())
                    || (int) $visit->getAttribute('agent_id') === $request->user()?->id;
                $selected['can_delete'] = $this->canManage($request->user());
                // What deleting it would cancel on the bill, for the confirm prompt.
                $charge = Charge::query()->active()->where('service_visit_id', $visit->id)->with('invoice:id,number')->first();
                $selected['charge'] = $charge === null ? null : ['amount' => $charge->total(), 'invoice' => $charge->invoice?->number];
                // Recent chlorine/pH for this pool → the drawer's chemistry trend.
                $trendVisits = ServiceVisit::query()
                    ->where('pool_id', $visit->getAttribute('pool_id'))
                    ->where('status', 'completed')
                    ->latest('completed_at')
                    ->with('chemicalReading')
                    ->limit(8)->get()->reverse()->values();
                $selected['trend'] = ServiceVisit::chemistryTrend($trendVisits);
            }
        }

        return Inertia::render('reports/Index', [
            'visits' => $visits,
            'selected' => $selected,
            'sort' => $sort,
        ]);
    }

    public function update(UpdateReportRequest $request, ServiceVisit $visit, UpdateReport $action): RedirectResponse
    {
        $action->handle($visit, $request->validated());

        return back()->with('success', 'Report updated.');
    }

    /** Delete a report and undo what it did — see DeleteReport. Admins only. */
    public function destroy(Request $request, ServiceVisit $visit, DeleteReport $action): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $summary = [
            'pool' => $visit->pool()->withTrashed()->value('name'),
            'completed_on' => Tenant::localDate($visit->completed_at),
            'agent_id' => $visit->getAttribute('agent_id'),
        ];
        $charge = $action->handle($visit, $request->user());
        AuditLog::record($request->user(), 'report.deleted', $visit, [...$summary, 'charge_cancelled' => $charge?->total()]);

        return back()->with('success', 'Report deleted.'.($charge !== null ? ' Its charge was cancelled.' : ''));
    }

    /** @return array<string, mixed> */
    private function toDetail(ServiceVisit $visit): array
    {
        $reading = $visit->chemicalReading;

        return [
            'id' => $visit->id,
            'pool' => $visit->pool?->name,
            'service' => $visit->serviceSubscription?->serviceType?->name,
            // The pool's own healthy ranges, so the water test reads against what the tech works to.
            'targets' => $visit->pool?->custom_target_ranges,
            'customer' => $visit->pool?->customer?->displayName(),
            'customer_id' => $visit->pool?->customer?->getKey(),
            'agent' => $visit->agent?->displayName(),
            'agent_id' => $visit->agent?->getKey(),
            'completed_on' => $visit->completed_at?->toDateString(),
            'notes' => $visit->notes,
            'office_notes' => $visit->office_notes,
            // The work order the tech raised on this visit, if any.
            'work_order' => $visit->workOrder === null ? null : $visit->workOrder->only(['id', 'title', 'details', 'urgent', 'status']),
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
            'photos' => $visit->photos
                ->map(fn ($p) => $this->photoUrl($p->getAttribute('photo_path')))
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
