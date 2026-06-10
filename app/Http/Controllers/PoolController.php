<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChemicalReading;
use App\Models\Pool;
use App\Services\ChemistryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Pools screen — the canonical table + URL-driven drawer.
 * The list is the table; `?selected=ID` loads one pool's detail into the
 * drawer (bookmarkable, back-button-correct). Tenant scoping is automatic
 * via the global TenantScope on Pool.
 */
class PoolController extends Controller
{
    public function index(Request $request, ChemistryService $chem): Response
    {
        $this->authorizeStaff($request);

        $search = trim((string) $request->string('search'));

        $pools = Pool::query()
            ->with([
                'customer:id,first_name,last_name',
                'serviceLocation:id,pool_id,city',
                'subscriptions' => fn ($q) => $q->where('status', 'active')->with('agent:id,first_name,last_name'),
            ])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Pool $pool) => $this->toRow($pool));

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $pool = Pool::query()->with([
                'customer:id,first_name,last_name,email,phone',
                'serviceLocation',
                'subscriptions' => fn ($q) => $q->where('status', 'active')->with('agent:id,first_name,last_name'),
            ])->find($selectedId);
            if ($pool) {
                $selected = $this->toDetail($pool, $chem);
            }
        }

        return Inertia::render('pools/Index', [
            'pools' => $pools,
            'selected' => $selected,
            'filters' => ['search' => $search],
        ]);
    }

    /** Staff-only (tenant_admin / agent); customers use the portal. */
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
    }

    /** @return array<string, mixed> */
    private function toRow(Pool $pool): array
    {
        $sub = $pool->subscriptions->first();

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'type' => $pool->type,
            'sanitizer' => $pool->sanitizer_type,
            'customer' => $this->personName($pool->customer),
            'city' => $pool->serviceLocation?->getAttribute('city'),
            'cadence' => $sub?->scheduleLabel(),
            'agent' => $sub !== null ? $this->personName($sub->agent) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function toDetail(Pool $pool, ChemistryService $chem): array
    {
        $reading = ChemicalReading::query()
            ->whereHas('serviceVisit', fn ($q) => $q->where('pool_id', $pool->id))
            ->latest()
            ->first();

        $health = null;
        if ($reading !== null) {
            $lsi = $reading->lsi_score ?? $chem->calculateLSI([
                'temperature' => $reading->water_temperature,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'calcium_hardness' => $reading->calcium_hardness,
                'salt' => $reading->salt,
            ]);
            $health = $chem->getLSIStatus((float) $lsi);
        }

        $location = $pool->serviceLocation;

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'type' => $pool->type,
            'volume_gallons' => $pool->volume_gallons,
            'sanitizer' => $pool->sanitizer_type,
            'filter' => $pool->filter_type,
            'equipment' => array_values(array_filter([
                $pool->has_heater ? 'Heater' : null,
                $pool->has_automation ? 'Automation' : null,
                $pool->has_pool_cleaner ? 'Cleaner' : null,
                $pool->has_cover ? 'Cover' : null,
                $pool->has_water_feature ? 'Water feature' : null,
                $pool->has_auto_fill ? 'Auto-fill' : null,
            ])),
            'customer' => [
                'name' => $this->personName($pool->customer),
                'email' => $pool->customer?->getAttribute('email'),
                'phone' => $pool->customer?->getAttribute('phone'),
            ],
            'location' => $location !== null ? [
                'city' => $location->getAttribute('city'),
                'gate_code' => $location->getAttribute('gate_code'),
                'access_notes' => $location->getAttribute('access_notes'),
            ] : null,
            'subscriptions' => $pool->subscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'schedule' => $sub->scheduleLabel(),
                'agent' => $this->personName($sub->agent),
            ])->all(),
            'latest_reading' => $reading !== null ? [
                'taken_on' => $reading->created_at?->toDateString(),
                'free_chlorine' => $reading->free_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'health' => $health,
            ] : null,
        ];
    }

    /** Full name of a Customer or User (magic attributes), or an em dash. */
    private function personName(?Model $person): string
    {
        if ($person === null) {
            return '—';
        }
        $name = trim((string) $person->getAttribute('first_name').' '.(string) $person->getAttribute('last_name'));

        return $name !== '' ? $name : '—';
    }
}
