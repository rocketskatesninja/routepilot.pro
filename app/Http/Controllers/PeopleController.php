<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Support\PersonListBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office People screen — agents + customers unified in one table+drawer,
 * with a type-specific drawer (customer: pools + recent visits; agent:
 * contact + activity). The unified list comes from PersonListBuilder.
 */
class PeopleController extends Controller
{
    public function index(Request $request, PersonListBuilder $builder): Response
    {
        $this->authorizeStaff($request);
        $tenantId = (int) $request->user()?->tenant_id;

        $type = (string) $request->string('type');
        if (! in_array($type, ['all', 'customers', 'agents'], true)) {
            $type = 'all';
        }
        $search = trim((string) $request->string('search'));

        $people = $builder->paginate($tenantId, $type, $search)->withQueryString();

        $selected = null;
        $selectedType = (string) $request->string('selected_type');
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $selected = match ($selectedType) {
                'customer' => $this->customerDetail($selectedId),
                'agent' => $this->agentDetail($tenantId, $selectedId),
                default => null,
            };
        }

        return Inertia::render('people/Index', [
            'people' => $people,
            'counts' => $builder->counts($tenantId, $search),
            'selected' => $selected,
            'filters' => ['search' => $search, 'type' => $type],
            'canManage' => $request->user()?->role === 'tenant_admin',
        ]);
    }

    /** Staff-only (tenant_admin / agent). */
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && $user->tenant_id !== null && in_array($user->role, ['tenant_admin', 'agent'], true), 403);
    }

    /** @return array<string, mixed>|null */
    private function customerDetail(int $id): ?array
    {
        // Tenant-scoped via the global scope — a foreign id returns null.
        $customer = Customer::query()->with('pools:id,customer_id,name,type')->find($id);
        if ($customer === null) {
            return null;
        }

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $customer->pools->pluck('id'))
            ->where('status', 'completed')
            ->with('pool:id,name')
            ->latest('completed_at')
            ->limit(5)
            ->get();

        return [
            'type' => 'customer',
            'id' => $customer->id,
            'name' => $this->personName($customer),
            'email' => $customer->getAttribute('email'),
            'phone' => $customer->getAttribute('phone'),
            'city' => $customer->getAttribute('city'),
            'has_portal' => $customer->getAttribute('user_id') !== null,
            'pools' => $customer->pools->map(fn ($pool) => [
                'id' => $pool->id,
                'name' => $pool->getAttribute('name'),
                'type' => $pool->getAttribute('type'),
            ])->all(),
            'recent_visits' => $visits->map(fn (ServiceVisit $visit) => [
                'id' => $visit->id,
                'pool' => $visit->pool?->getAttribute('name'),
                'completed_on' => $visit->completed_at?->toDateString(),
            ])->all(),
            // Raw values for the edit form.
            'fields' => [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_line1' => $customer->address_line1,
                'city' => $customer->city,
                'state' => $customer->state,
                'zip' => $customer->zip,
                'notes' => $customer->notes,
                'bill_chemicals' => (bool) $customer->getAttribute('bill_chemicals'),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function agentDetail(int $tenantId, int $id): ?array
    {
        // User is not globally tenant-scoped — filter explicitly.
        $agent = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'agent')
            ->find($id);
        if ($agent === null) {
            return null;
        }

        $completed = ServiceVisit::query()->where('agent_id', $agent->id)->where('status', 'completed')->count();
        $thisWeek = ServiceVisit::query()
            ->where('agent_id', $agent->id)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        return [
            'type' => 'agent',
            'id' => $agent->id,
            'name' => $this->personName($agent),
            'email' => $agent->getAttribute('email'),
            'phone' => $agent->getAttribute('phone'),
            'is_active' => (bool) $agent->getAttribute('is_active'),
            'stats' => ['completed_visits' => $completed, 'this_week' => $thisWeek],
        ];
    }

    private function personName(Model $person): string
    {
        $name = trim((string) $person->getAttribute('first_name').' '.(string) $person->getAttribute('last_name'));

        return $name !== '' ? $name : '—';
    }
}
