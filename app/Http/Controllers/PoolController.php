<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePool;
use App\Actions\UpdatePool;
use App\Http\Requests\StorePoolRequest;
use App\Http\Requests\UpdatePoolRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\EquipmentServiceLog;
use App\Models\Pool;
use App\Models\PoolEquipment;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Services\ChemistryService;
use App\Support\OptionLists;
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

        $query = Pool::query()
            ->with([
                'customer:id,first_name,last_name,photo_path',
                'serviceLocation:id,pool_id,city',
                'subscriptions' => fn ($q) => $q->where('status', 'active')->with('agent:id,first_name,last_name,avatar_path'),
                'latestReading',
            ])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));

        $sort = $this->applySort($query, $request, [
            'name' => 'name',
            'type' => 'type',
            'customer' => fn ($q, $dir) => $q->orderBy(Customer::query()->select('first_name')->whereColumn('customers.id', 'pools.customer_id'), $dir),
        ], 'name');

        $pools = $query
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Pool $pool) => $this->toRow($pool, $chem));

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $pool = Pool::query()->with([
                'customer:id,first_name,last_name,email,phone',
                'serviceLocation',
                // Active first, then paused, then ended (kept visible so they can be restarted).
                'subscriptions' => fn ($q) => $q->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")->orderBy('id')
                    ->with(['agent:id,first_name,last_name,role', 'serviceType:id,name']),
                'latestReading',
            ])->find($selectedId);
            if ($pool) {
                $selected = $this->toDetail($pool, $chem);
            }
        }

        return Inertia::render('pools/Index', [
            'pools' => $pools,
            'selected' => $selected,
            'filters' => ['search' => $search],
            'sort' => $sort,
            'customers' => OptionLists::customers(),
            'serviceTypes' => OptionLists::serviceTypes(),
            'agents' => OptionLists::agents(),
            'canManage' => $this->canManage($request->user()),
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
        $this->authorizeAdmin($request);

        AuditLog::record($request->user(), 'pool.deleted', $pool);
        $pool->delete();

        return back()->with('success', 'Pool removed.');
    }

    /** Save per-pool chemistry target overrides (blanks fall back to defaults). */
    public function updateTargets(Request $request, Pool $pool): RedirectResponse
    {
        $this->authorizeAdmin($request);

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
            'photo_url' => $this->photoUrl($pool->getAttribute('photo_path')),
            'type' => $pool->type,
            'sanitizer' => $pool->sanitizer_type,
            'customer' => $pool->customer?->displayName() ?? '—',
            'customer_photo' => $this->photoUrl($pool->customer?->getAttribute('photo_path')),
            'city' => $pool->serviceLocation?->getAttribute('city'),
            'cadence' => $sub === null ? null : $sub->scheduleLabel().($pool->subscriptions->count() > 1 ? ' +'.($pool->subscriptions->count() - 1).' more' : ''),
            'agent' => $sub !== null ? ($sub->agent?->displayName() ?? '—') : null,
            'agent_photo' => $sub?->agent !== null ? $this->photoUrl($sub->agent->getAttribute('avatar_path')) : null,
            'health' => $health,
        ];
    }

    /**
     * Visits nobody made (stops left unfinished after their day, see MarkMissedStops):
     * how many in all, and the latest few with their day and service.
     *
     * @return array{count: int, recent: list<array{id: int, on: string|null, service: string|null}>}
     */
    private function missedVisits(Pool $pool): array
    {
        $missed = RouteStop::query()->where('pool_id', $pool->id)->where('status', 'missed');

        $recent = (clone $missed)
            ->with(['route:id,scheduled_date', 'serviceSubscription.serviceType:id,name'])
            ->orderByDesc(Route::query()->select('scheduled_date')->whereColumn('routes.id', 'route_stops.route_id'))
            ->limit(5)->get()
            ->map(fn (RouteStop $s): array => [
                'id' => $s->id,
                'on' => $s->route?->scheduled_date?->toDateString(),
                'service' => $s->serviceSubscription?->serviceType?->name,
            ])->all();

        return ['count' => $missed->count(), 'recent' => $recent];
    }

    /** @return array<string, mixed> */
    private function toDetail(Pool $pool, ChemistryService $chem): array
    {
        // Single source of truth: the same relation the list rows use, so the detail
        // pane and the list's health dot can never disagree on "the latest reading".
        $reading = $pool->latestReading;

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
        $lastReportId = ServiceVisit::query()->where('pool_id', $pool->id)->where('status', 'completed')->latest('completed_at')->value('id');

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'photo_url' => $this->photoUrl($pool->getAttribute('photo_path')),
            'report_cc' => $pool->report_cc_email, // who else gets every visit report
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
            // The same feature keys the portal describes a pool with.
            'features' => array_keys(array_filter([
                'heater' => $pool->has_heater, 'cover' => $pool->has_cover, 'automation' => $pool->has_automation,
                'cleaner' => $pool->has_pool_cleaner, 'water_feature' => $pool->has_water_feature, 'auto_fill' => $pool->has_auto_fill,
            ])),
            'customer' => [
                'id' => $pool->customer?->getKey(),
                'name' => $pool->customer?->displayName() ?? '—',
                'email' => $pool->customer?->getAttribute('email'),
                'phone' => $pool->customer?->getAttribute('phone'),
            ],
            'location' => $location !== null ? [
                'address' => $location->getAttribute('address_line1'),
                'city' => $location->getAttribute('city'),
                'gate_code' => $location->getAttribute('gate_code'),
                'access_notes' => $location->getAttribute('access_notes'),
            ] : null,
            'subscriptions' => $pool->subscriptions->map(fn (ServiceSubscription $sub): array => [
                'id' => $sub->id,
                'service' => $sub->serviceType->name,
                'schedule' => $sub->scheduleLabel(),
                'agent' => $sub->agent?->displayName() ?? '—',
                'status' => $sub->status,
                'service_type_id' => $sub->service_type_id,
                'agent_id' => $sub->assigned_agent_id,
                'agent_is_tech' => $sub->agent?->role === 'agent', // People opens techs only, so only they're linked
                'frequency' => $sub->frequency,
                'preferred_day' => $sub->preferred_day,
                'starts_on' => $sub->starts_on?->toDateString(),
                'ends_on' => $sub->ends_on?->toDateString(),
                'day_of_month' => $sub->day_of_month,
                'hold_label' => $sub->hold_starts_at !== null && $sub->hold_ends_at !== null && $sub->hold_ends_at->toDateString() >= Tenant::localToday()->toDateString()
                    ? 'On hold '.$sub->hold_starts_at->format('M j').' – '.$sub->hold_ends_at->format('M j')
                    : null,
                'hold_starts_at' => $sub->hold_starts_at?->toDateString(),
                'hold_ends_at' => $sub->hold_ends_at?->toDateString(),
            ])->all(),
            'missed' => $this->missedVisits($pool),
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
                'taken_on' => Tenant::localDate($reading->serviceVisit?->completed_at),
                'free_chlorine' => $reading->free_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'calcium_hardness' => $reading->calcium_hardness,
                'cyanuric_acid' => $reading->cyanuric_acid,
                'salt' => $reading->salt,
                'health' => $health,
                'last_report_id' => $lastReportId,
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
                'report_cc_email' => $pool->report_cc_email,
            ],
        ];
    }
}
