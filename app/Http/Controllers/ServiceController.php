<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ServiceType;
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

        $services = ServiceType::query()
            ->withCount(['subscriptions as active_pools_count' => fn ($q) => $q->where('status', 'active')])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
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
        ]);
    }

    /** Staff-only (tenant_admin / agent). */
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
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
        ];
    }
}
