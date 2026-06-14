<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendCampaign;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PlatformAiSettings;
use App\Support\PersonListBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office People screen — agents + customers unified in one table+drawer,
 * with a type-specific drawer (customer: pools + recent visits; agent:
 * contact + activity). The unified list comes from PersonListBuilder.
 */
class PeopleController extends Controller
{
    public function index(Request $request, PersonListBuilder $builder, SendCampaign $campaigns, BillingService $billing): Response
    {
        $user = $request->user();
        if ($user?->isSuperAdmin()) {
            return $this->platform($request, $user, $campaigns);
        }

        $this->authorizeStaff($request);
        $tenantId = (int) $user?->tenant_id;

        $type = (string) $request->string('type');
        if (! in_array($type, ['all', 'customers', 'agents'], true)) {
            $type = 'all';
        }
        $search = trim((string) $request->string('search'));

        $sortKey = (string) $request->string('sort');
        if (! in_array($sortKey, ['name', 'type', 'email', 'phone'], true)) {
            $sortKey = 'name';
        }
        $sortDir = strtolower((string) $request->string('dir')) === 'desc' ? 'desc' : 'asc';

        $people = $builder->paginate($tenantId, $type, $search, $this->perPage($request), $sortKey, $sortDir)->withQueryString();

        // Enrich the current page's customer rows with balance + last visit (batched).
        $customerIds = $people->getCollection()
            ->filter(fn (object $r): bool => $r->person_type === 'customer')
            ->map(fn (object $r): int => (int) $r->id)
            ->values()->all();
        $balances = $billing->balancesFor($customerIds);
        $lastVisits = $this->lastVisitsFor($customerIds);
        $people->setCollection($people->getCollection()->map(function (object $r) use ($balances, $lastVisits): object {
            $isCustomer = $r->person_type === 'customer';
            $r->balance = $isCustomer ? ($balances[(int) $r->id] ?? 0.0) : null;
            $r->last_visit = $isCustomer ? ($lastVisits[(int) $r->id] ?? null) : null;
            $r->photo_url = $this->photoUrl($r->photo ?? null);

            return $r;
        }));

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
            'sort' => ['key' => $sortKey, 'dir' => $sortDir],
            'canManage' => $this->canManage($user),
            'canEmail' => $this->canManage($user),
            'audiences' => $this->canManage($user) ? $campaigns->audiencesFor($user) : [],
            'recent' => $this->recentCampaigns(),
        ]);
    }

    /**
     * Most-recent completed-visit date per customer (one query).
     *
     * @param  list<int>  $customerIds
     * @return array<int, string|null>
     */
    private function lastVisitsFor(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        return ServiceVisit::query()
            ->join('pools', 'service_visits.pool_id', '=', 'pools.id')
            ->whereIn('pools.customer_id', $customerIds)
            ->where('service_visits.status', 'completed')
            ->groupBy('pools.customer_id')
            ->selectRaw('pools.customer_id as cid, max(service_visits.completed_at) as last')
            ->pluck('last', 'cid')
            ->map(fn ($d): ?string => $d !== null ? Carbon::parse((string) $d)->toDateString() : null)
            ->all();
    }

    /** Super-admin platform-wide People (manage + broadcast), table+drawer like the tenant screen. */
    private function platform(Request $request, User $user, SendCampaign $campaigns): Response
    {
        $type = (string) $request->string('type');
        if (! in_array($type, ['tenants', 'agents', 'customers'], true)) {
            $type = 'tenants';
        }
        $search = trim((string) $request->string('search'));

        // Selecting a row shows that entity's detail in the drawer (tenant = the
        // management pane; agent/customer = a read-only card).
        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $selected = match ((string) $request->string('selected_type')) {
                'tenant' => $this->tenantDetail($selectedId),
                'agent' => $this->platformPersonDetail('agent', $selectedId),
                'customer' => $this->platformPersonDetail('customer', $selectedId),
                default => null,
            };
        }

        return Inertia::render('people/Platform', [
            'audiences' => $campaigns->audiencesFor($user),
            'counts' => [
                'tenants' => Tenant::query()->count(),
                'agents' => User::query()->where('role', 'agent')->count(),
                'customers' => Customer::query()->count(),
            ],
            'people' => $this->platformPeople($type, $search),
            'filters' => ['type' => $type, 'search' => $search],
            'selected' => $selected,
            'aiDefaultQuota' => app(PlatformAiSettings::class)->defaultQuota(),
            'recent' => $this->recentCampaigns(true),
        ]);
    }

    /**
     * Tenant management detail (counts + per-tenant AI) for the drawer.
     *
     * @return array<string, mixed>|null
     */
    private function tenantDetail(int $id): ?array
    {
        $t = Tenant::query()->withCount(['users', 'pools'])->find($id);
        if ($t === null) {
            return null;
        }

        $ai = app(PlatformAiSettings::class)->tenantAi([$t->id])[$t->id]
            ?? ['enabled' => true, 'allow_override' => false, 'quota' => null, 'limit' => 0, 'used' => 0];

        return [
            'type' => 'tenant',
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'status' => $t->getAttribute('status'),
            'pools' => $t->getAttribute('pools_count'),
            'users' => $t->getAttribute('users_count'),
            'created' => $t->created_at?->toDateString(),
            'logo_url' => $this->photoUrl($t->getAttribute('logo_path')),
            'ai' => $ai,
        ];
    }

    /**
     * Read-only card for an agent/customer (they belong to a tenant).
     *
     * @return array<string, mixed>|null
     */
    private function platformPersonDetail(string $type, int $id): ?array
    {
        if ($type === 'agent') {
            $u = User::query()->where('role', 'agent')->with('tenant:id,name')->find($id);
            if ($u === null) {
                return null;
            }

            return [
                'type' => 'agent',
                'id' => $u->id,
                'name' => $u->displayName(),
                'email' => $u->getAttribute('email'),
                'phone' => $u->getAttribute('phone'),
                'tenant' => $u->tenant?->name,
                'photo_url' => $this->photoUrl($u->getAttribute('avatar_path')),
                'is_active' => (bool) $u->getAttribute('is_active'),
            ];
        }

        $c = Customer::query()->with('tenant:id,name')->find($id);
        if ($c === null) {
            return null;
        }

        return [
            'type' => 'customer',
            'id' => $c->id,
            'name' => $c->displayName(),
            'email' => $c->email,
            'phone' => $c->getAttribute('phone'),
            'tenant' => $c->tenant?->name,
            'photo_url' => $this->photoUrl($c->getAttribute('photo_path')),
        ];
    }

    /**
     * Platform-wide people of one type, as pickable rows for the super-admin.
     * A "tenant" row resolves (at send time) to that company's admins.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function platformPeople(string $type, string $search): LengthAwarePaginator
    {
        $like = '%'.$search.'%';
        $nameSearch = fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like));

        return match ($type) {
            'agents' => User::query()->where('role', 'agent')->with('tenant:id,name')
                ->when($search !== '', $nameSearch)
                ->orderBy('first_name')->orderBy('last_name')
                ->paginate(25)->withQueryString()
                ->through(fn (User $u): array => ['key' => 'agent:'.$u->id, 'id' => $u->id, 'type' => 'agent', 'name' => $u->displayName(), 'sub' => $u->getAttribute('email'), 'meta' => $u->tenant?->name]),
            'customers' => Customer::query()->with('tenant:id,name')
                ->when($search !== '', $nameSearch)
                ->orderBy('first_name')->orderBy('last_name')
                ->paginate(25)->withQueryString()
                ->through(fn (Customer $c): array => ['key' => 'customer:'.$c->id, 'id' => $c->id, 'type' => 'customer', 'name' => $c->displayName(), 'sub' => $c->email, 'meta' => $c->tenant?->name]),
            default => Tenant::query()
                ->when($search !== '', fn ($q) => $q->where('name', 'like', $like))
                ->orderBy('name')
                ->paginate(25)->withQueryString()
                ->through(fn (Tenant $t): array => ['key' => 'tenant:'.$t->id, 'id' => $t->id, 'type' => 'tenant', 'name' => $t->name, 'sub' => $t->getAttribute('status'), 'meta' => $t->slug]),
        };
    }

    /**
     * Recent campaigns for the composer. Platform = tenant-less (super) sends;
     * otherwise the tenant's own (via the global scope).
     *
     * @return list<array<string, mixed>>
     */
    private function recentCampaigns(bool $platform = false): array
    {
        return MailCampaign::query()
            ->when($platform, fn ($q) => $q->whereNull('tenant_id'))
            ->latest('sent_at')
            ->limit(12)
            ->get()
            ->map(fn (MailCampaign $c): array => [
                'id' => $c->id,
                'subject' => $c->subject,
                'audience' => $c->audience,
                'recipients' => $c->recipient_count,
                'sent_on' => $c->sent_at?->toDateString(),
            ])->all();
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
            'name' => $customer->displayName(),
            'photo_url' => $this->photoUrl($customer->getAttribute('photo_path')),
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
            'name' => $agent->displayName(),
            'photo_url' => $this->photoUrl($agent->getAttribute('avatar_path')),
            'email' => $agent->getAttribute('email'),
            'phone' => $agent->getAttribute('phone'),
            'is_active' => (bool) $agent->getAttribute('is_active'),
            'agent_plus' => (bool) $agent->getAttribute('agent_plus'),
            'stats' => ['completed_visits' => $completed, 'this_week' => $thisWeek],
            // Raw values for the edit form.
            'fields' => [
                'first_name' => $agent->getAttribute('first_name'),
                'last_name' => $agent->getAttribute('last_name'),
                'email' => $agent->getAttribute('email'),
                'phone' => $agent->getAttribute('phone'),
                'map_color' => $agent->getAttribute('map_color'),
                'is_active' => (bool) $agent->getAttribute('is_active'),
                'agent_plus' => (bool) $agent->getAttribute('agent_plus'),
            ],
        ];
    }
}
