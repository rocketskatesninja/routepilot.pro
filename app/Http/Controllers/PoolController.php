<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePool;
use App\Actions\UpdatePool;
use App\Http\Requests\StorePoolRequest;
use App\Http\Requests\UpdatePoolRequest;
use App\Models\ChemicalReading;
use App\Models\Customer;
use App\Models\EquipmentServiceLog;
use App\Models\Pool;
use App\Models\PoolEquipment;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\ChemistryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
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
                'latestReading',
            ])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Pool $pool) => $this->toRow($pool, $chem));

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $pool = Pool::query()->with([
                'customer:id,first_name,last_name,email,phone',
                'serviceLocation',
                'subscriptions' => fn ($q) => $q->whereIn('status', ['active', 'paused'])->with(['agent:id,first_name,last_name', 'serviceType:id,name']),
            ])->find($selectedId);
            if ($pool) {
                $selected = $this->toDetail($pool, $chem);
            }
        }

        return Inertia::render('pools/Index', [
            'pools' => $pools,
            'selected' => $selected,
            'filters' => ['search' => $search],
            'customers' => $this->customerOptions(),
            'serviceTypes' => $this->serviceTypeOptions(),
            'agents' => $this->agentOptions(),
            'canManage' => $request->user()?->role === 'tenant_admin',
        ]);
    }

    public function store(StorePoolRequest $request, CreatePool $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Pool added.');
    }

    public function update(UpdatePoolRequest $request, Pool $pool, UpdatePool $action): RedirectResponse
    {
        $action->handle($pool, $request->validated());

        return back()->with('success', 'Pool updated.');
    }

    public function destroy(Request $request, Pool $pool): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);
        $pool->delete();

        return back()->with('success', 'Pool removed.');
    }

    /** Save per-pool chemistry target overrides (blanks fall back to defaults). */
    public function updateTargets(Request $request, Pool $pool): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $validated = $request->validate([
            'targets' => ['array'],
            'targets.*.min' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'targets.*.max' => ['nullable', 'numeric', 'min:0', 'max:50000'],
        ]);

        $ranges = [];
        foreach (['free_chlorine', 'ph', 'alkalinity', 'calcium_hardness', 'cyanuric_acid', 'salt'] as $param) {
            $min = $validated['targets'][$param]['min'] ?? null;
            $max = $validated['targets'][$param]['max'] ?? null;
            $override = array_filter([
                'min' => $min !== null ? (float) $min : null,
                'max' => $max !== null ? (float) $max : null,
            ], fn ($v): bool => $v !== null);
            if ($override !== []) {
                $ranges[$param] = $override;
            }
        }

        $pool->update(['custom_target_ranges' => $ranges === [] ? null : $ranges]);

        return back()->with('success', 'Chemistry targets saved.');
    }

    /** @return list<array{id: int, name: string}> */
    private function customerOptions(): array
    {
        return Customer::query()
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Customer $c): array => ['id' => $c->id, 'name' => $c->displayName()])
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function serviceTypeOptions(): array
    {
        return ServiceType::query()
            ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (ServiceType $t): array => ['id' => $t->id, 'name' => $t->name])
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function agentOptions(): array
    {
        return User::query()
            ->where('tenant_id', app('tenant_id'))->where('role', 'agent')->where('is_active', true)
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->displayName()])
            ->all();
    }

    /** Staff-only (tenant_admin / agent); customers use the portal. */
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
    }

    /** @return array<string, mixed> */
    private function toRow(Pool $pool, ChemistryService $chem): array
    {
        $sub = $pool->subscriptions->first();

        $health = null;
        $reading = $pool->latestReading;
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

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'type' => $pool->type,
            'sanitizer' => $pool->sanitizer_type,
            'customer' => $this->personName($pool->customer),
            'city' => $pool->serviceLocation?->getAttribute('city'),
            'cadence' => $sub?->scheduleLabel(),
            'agent' => $sub !== null ? $this->personName($sub->agent) : null,
            'health' => $health,
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
            'subscriptions' => $pool->subscriptions->map(fn (ServiceSubscription $sub): array => [
                'id' => $sub->id,
                'service' => $sub->serviceType->name,
                'schedule' => $sub->scheduleLabel(),
                'agent' => $this->personName($sub->agent),
                'status' => $sub->status,
                'service_type_id' => $sub->service_type_id,
                'agent_id' => $sub->assigned_agent_id,
                'frequency' => $sub->frequency,
                'preferred_day' => $sub->preferred_day,
                'hold_starts_at' => $sub->hold_starts_at?->toDateString(),
                'hold_ends_at' => $sub->hold_ends_at?->toDateString(),
            ])->all(),
            'equipment_items' => $pool->equipmentItems()->with('serviceLog')->latest('id')->get()->map(fn (PoolEquipment $e): array => [
                'id' => $e->id,
                'type' => $e->type,
                'make' => $e->getAttribute('make'),
                'model' => $e->getAttribute('model'),
                'serial' => $e->getAttribute('serial'),
                'installed_on' => $e->installed_on?->toDateString(),
                'warranty_until' => $e->warranty_until?->toDateString(),
                'notes' => $e->getAttribute('notes'),
                'service_log' => $e->serviceLog->map(fn (EquipmentServiceLog $l): array => [
                    'id' => $l->id,
                    'on' => $l->serviced_on?->toDateString(),
                    'description' => $l->description,
                    'cost' => (float) $l->cost,
                ])->all(),
            ])->all(),
            'targets' => $pool->custom_target_ranges ?? [],
            'latest_reading' => $reading !== null ? [
                'taken_on' => $reading->created_at?->toDateString(),
                'free_chlorine' => $reading->free_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'health' => $health,
            ] : null,
            // Raw values for the edit form.
            'fields' => [
                'customer_id' => $pool->customer_id,
                'name' => $pool->name,
                'type' => $pool->type,
                'volume_gallons' => $pool->volume_gallons,
                'surface_type' => $pool->surface_type,
                'sanitizer_type' => $pool->sanitizer_type,
                'filter_type' => $pool->filter_type,
                'pump_type' => $pool->pump_type,
                'has_heater' => $pool->has_heater,
                'has_automation' => $pool->has_automation,
                'has_pool_cleaner' => $pool->has_pool_cleaner,
                'has_cover' => $pool->has_cover,
                'has_water_feature' => $pool->has_water_feature,
                'has_auto_fill' => $pool->has_auto_fill,
                'notes' => $pool->notes,
                'address_line1' => $location?->getAttribute('address_line1'),
                'city' => $location?->getAttribute('city'),
                'state' => $location?->getAttribute('state'),
                'zip' => $location?->getAttribute('zip'),
                'gate_code' => $location?->getAttribute('gate_code'),
                'access_notes' => $location?->getAttribute('access_notes'),
            ],
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
