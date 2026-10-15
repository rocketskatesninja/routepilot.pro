<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveServiceType;
use App\Http\Requests\ServiceTypeRequest;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office Services screen — the company's service catalog. Each entry says
 * what the service is, how it's sold, what it costs, what the tech does, and
 * whether customers can request it.
 */
class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeStaff($request);

        $search = trim((string) $request->string('search'));
        $activePlans = ['subscriptions as active_pools_count' => fn ($q) => $q->where('status', 'active')];

        $query = ServiceType::query()
            ->withCount($activePlans)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));

        $sort = $this->applySort($query, $request, [
            'name' => 'name',
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
                'frequency' => $type->frequency,
                'price' => $type->price,
                'pools' => (int) $type->getAttribute('active_pools_count'),
                'is_active' => $type->is_active,
                'requestable' => $type->requestable,
            ]);

        $selectedId = $request->integer('selected');
        $selected = $selectedId > 0 ? ServiceType::query()->withCount($activePlans)->find($selectedId) : null;

        return Inertia::render('services/Index', [
            'services' => $services,
            'selected' => $selected !== null ? $this->toDetail($selected) : null,
            'filters' => ['search' => $search],
            'sort' => $sort,
            'canManage' => $this->canManage($request->user()),
        ]);
    }

    public function store(ServiceTypeRequest $request, SaveServiceType $action): RedirectResponse
    {
        $type = $action->handle($request->validated());

        return redirect()->route('services.index', ['selected' => $type->id])->with('success', 'Service added.');
    }

    public function update(ServiceTypeRequest $request, ServiceType $service, SaveServiceType $action): RedirectResponse
    {
        $action->handle($request->validated(), $service);

        return back()->with('success', 'Service saved.');
    }

    public function destroy(Request $request, ServiceType $service): RedirectResponse
    {
        $this->authorizeAdmin($request);

        // Never cascade-delete plan history — retire a used service by making it inactive.
        if ($service->subscriptions()->exists()) {
            return back()->with('error', 'This service is on pools\' plans, so it can\'t be removed. Mark it inactive instead.');
        }

        $service->delete();

        return redirect()->route('services.index')->with('success', 'Service removed.');
    }

    /** @return array<string, mixed> */
    private function toDetail(ServiceType $type): array
    {
        $modules = $type->modules();

        return [
            'id' => $type->id,
            'name' => $type->name,
            'description' => $type->description,
            'frequency' => $type->frequency,
            'price' => $type->price,
            'offer' => $type->offerLabel(),
            'duration_minutes' => $type->estimated_duration_minutes,
            'modules' => array_values(array_filter(ServiceType::MODULES, fn (string $key): bool => $modules[$key], ARRAY_FILTER_USE_KEY)),
            'tasks' => array_values($type->tasks ?? []),
            'pools' => (int) $type->getAttribute('active_pools_count'),
            'is_active' => $type->is_active,
            'requestable' => $type->requestable,
            // Raw values for the edit form.
            'fields' => [
                'name' => $type->name,
                'description' => $type->description,
                'frequency' => $type->frequency,
                'price' => $type->price,
                'estimated_duration_minutes' => $type->estimated_duration_minutes,
                'tasks' => array_values($type->tasks ?? []),
                'field_modules' => $modules,
                'is_active' => $type->is_active,
                'requestable' => $type->requestable,
            ],
        ];
    }
}
