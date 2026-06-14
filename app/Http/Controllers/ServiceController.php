<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateServiceType;
use App\Actions\UpdateServiceType;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Services screen — the tenant's service-type catalog on the
 * table+drawer pattern. Each row is a reusable visit template; the drawer
 * shows pricing, the field-flow modules, and the task checklist.
 */
class ServiceController extends Controller
{
    /** Field-flow modules a service type can switch on, in display order. */
    private const MODULES = ['tasks' => 'Tasks', 'chemistry' => 'Chemistry', 'treatments' => 'Treatments', 'photos' => 'Photos'];

    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $search = trim((string) $request->string('search'));

        $query = ServiceType::query()
            ->withCount(['subscriptions as active_pools_count' => fn ($q) => $q->where('status', 'active')])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));

        $sort = $this->applySort($query, $request, [
            'name' => 'name',
            'category' => 'category',
            'price' => 'price',
            'pools' => 'active_pools_count',
            'status' => 'is_active',
        ], 'name');

        $services = $query
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ServiceType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'category' => $type->category,
                'frequency' => $type->frequency,
                'price' => $type->price,
                'pools' => (int) $type->getAttribute('active_pools_count'),
                'is_active' => $type->is_active,
            ]);

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $type = ServiceType::query()
                ->withCount(['subscriptions as active_pools_count' => fn ($q) => $q->where('status', 'active')])
                ->find($selectedId);
            if ($type !== null) {
                $selected = $this->toDetail($type);
            }
        }

        return Inertia::render('services/Index', [
            'services' => $services,
            'selected' => $selected,
            'filters' => ['search' => $search],
            'sort' => $sort,
            'canManage' => $request->user()?->role === 'tenant_admin',
        ]);
    }

    public function store(StoreServiceTypeRequest $request, CreateServiceType $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Service type added.');
    }

    public function update(UpdateServiceTypeRequest $request, ServiceType $service, UpdateServiceType $action): RedirectResponse
    {
        $action->handle($service, $request->validated());

        return back()->with('success', 'Service type updated.');
    }

    public function destroy(Request $request, ServiceType $service): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        // Never cascade-delete subscription history — retire via inactive instead.
        if ($service->subscriptions()->exists()) {
            return back()->with('error', 'This service type is in use. Mark it inactive instead.');
        }

        $service->delete();

        return back()->with('success', 'Service type removed.');
    }

    /** @return array<string, mixed> */
    private function toDetail(ServiceType $type): array
    {
        // Which at-pool modules this service shows (default: all enabled).
        $flags = $type->field_modules ?? [];
        $modules = [];
        foreach (self::MODULES as $key => $label) {
            if (! array_key_exists($key, $flags) || $flags[$key]) {
                $modules[] = $label;
            }
        }

        return [
            'id' => $type->id,
            'name' => $type->name,
            'category' => $type->category,
            'frequency' => $type->frequency,
            'duration_minutes' => $type->estimated_duration_minutes,
            'price' => $type->price,
            'chemicals_included' => $type->chemicals_included,
            'description' => $type->description,
            'modules' => $modules,
            'tasks' => array_values($type->tasks ?? []),
            'pools' => (int) $type->getAttribute('active_pools_count'),
            'is_active' => $type->is_active,
            // Raw values for the edit form.
            'fields' => [
                'name' => $type->name,
                'category' => $type->category,
                'frequency' => $type->frequency,
                'estimated_duration_minutes' => $type->estimated_duration_minutes,
                'price' => $type->price,
                'chemicals_included' => $type->chemicals_included,
                'description' => $type->description,
                'tasks' => array_values($type->tasks ?? []),
                'field_modules' => $this->moduleFlags($type),
                'is_active' => $type->is_active,
            ],
        ];
    }

    /**
     * Current module on/off flags (default all on), keyed for the form.
     *
     * @return array<string, bool>
     */
    private function moduleFlags(ServiceType $type): array
    {
        $flags = $type->field_modules ?? [];
        $out = [];
        foreach (array_keys(self::MODULES) as $key) {
            $out[$key] = ! array_key_exists($key, $flags) || (bool) $flags[$key];
        }

        return $out;
    }
}
