<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SendCampaign;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MailCampaign;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PlatformAiSettings;
use App\Support\EmailPreferences;
use App\Support\PersonListBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office People screen — agents + customers unified in one table+drawer,
 * with a type-specific drawer (customer: pools + recent visits; agent:
 * contact + activity). The unified list comes from PersonListBuilder.
 */
class PeopleController extends Controller
{
    public function index(Request $request, PersonListBuilder $builder, SendCampaign $campaigns): Response
    {
        $user = $request->user();
        if ($user?->isSuperAdmin()) {
            return $this->platform($request, $user, $campaigns);
        }

        $this->authorizeStaff($request);
        $tenantId = (int) $user?->tenant_id;

        $filters = PersonListBuilder::filters($request);
        $type = $filters['type'];

        $people = $builder->paginate($tenantId, $filters, $this->perPage($request))->withQueryString();
        // The current page's customer rows get their balance, last visit and portal login (batched).
        $people->setCollection($builder->withDetails($people->getCollection())->map(function (object $r): object {
            $r->photo_url = $this->photoUrl($r->photo ?? null);

            return $r;
        }));

        $selected = null;
        $selectedType = (string) $request->string('selected_type');
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $selected = match ($selectedType) {
                'customer' => $this->customerDetail($selectedId, $type === 'archived'),
                'agent' => $this->agentDetail($tenantId, $selectedId),
                default => null,
            };
        }

        return Inertia::render('people/Index', [
            'people' => $people,
            'counts' => $builder->counts($tenantId, $filters['search']),
            'selected' => $selected,
            'filters' => ['search' => $filters['search'], 'type' => $type],
            'sort' => ['key' => $filters['sort'], 'dir' => $filters['dir']],
            'canManage' => $this->canManage($user),
            'canEmail' => $this->canManage($user),
            'audiences' => $this->canManage($user) ? $campaigns->audiencesFor($user) : [],
            'recent' => $this->recentCampaigns(),
        ]);
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
                'login_id' => $u->id,
            ];
        }

        $c = Customer::query()->with(['tenant:id,name', 'user:id,role'])->find($id);
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
            // Their portal login to sign in as (null = no portal access yet).
            'login_id' => $c->user?->role === 'customer' ? $c->user->id : null,
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
            'customers' => Customer::query()->with(['tenant:id,name', 'user:id,role'])
                ->when($search !== '', $nameSearch)
                ->orderBy('first_name')->orderBy('last_name')
                ->paginate(25)->withQueryString()
                // portal = has a login the super-admin can sign in as
                ->through(fn (Customer $c): array => ['key' => 'customer:'.$c->id, 'id' => $c->id, 'type' => 'customer', 'name' => $c->displayName(), 'sub' => $c->email, 'meta' => $c->tenant?->name, 'portal' => $c->user?->role === 'customer']),
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
                'sent' => $c->sent_count,
                'failed' => $c->failed_count,
                'sent_on' => $c->sent_at?->toDateString(),
            ])->all();
    }

    /** @return array<string, mixed>|null */
    private function customerDetail(int $id, bool $archived = false): ?array
    {
        // Tenant-scoped via the global scope — a foreign id returns null. Archived
        // customers (and their archived pools) are only reachable withTrashed.
        $customer = ($archived ? Customer::withTrashed() : Customer::query())
            ->with(['pools' => fn ($q) => ($archived ? $q->withTrashed() : $q)->select('id', 'customer_id', 'name', 'type', 'volume_gallons')])
            ->find($id);
        if ($customer === null) {
            return null;
        }

        $poolIds = $customer->pools->pluck('id');
        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'completed')
            ->with(['pool:id,name', 'agent:id,first_name,last_name', 'serviceSubscription.serviceType:id,name'])
            ->latest('completed_at')
            ->limit(5)
            ->get();

        // What each pool gets now, and when someone is next out.
        $services = ServiceSubscription::query()->whereIn('pool_id', $poolIds)->current()->with('serviceType:id,name')->get()->groupBy('pool_id');
        $next = RouteStop::query()->select('route_stops.*')
            ->join('routes', 'routes.id', '=', 'route_stops.route_id')
            ->whereIn('route_stops.pool_id', $poolIds)
            ->where('route_stops.status', 'pending')
            ->where('routes.scheduled_date', '>=', Tenant::localToday()->toDateString())
            ->with(['route:id,scheduled_date,agent_id', 'route.agent:id,first_name,last_name', 'pool:id,name'])
            ->orderBy('routes.scheduled_date')
            ->first();

        return [
            'type' => 'customer',
            'id' => $customer->id,
            'name' => $customer->displayName(),
            'photo_url' => $this->photoUrl($customer->getAttribute('photo_path')),
            'email' => $customer->getAttribute('email'),
            'phone' => $customer->getAttribute('phone'),
            'city' => $customer->getAttribute('city'),
            'address' => implode(', ', array_filter([$customer->address_line1, $customer->city, $customer->state])) ?: null,
            'since' => Tenant::localDate($customer->getAttribute('onboarded_at') ?? $customer->created_at),
            'balance' => app(BillingService::class)->outstandingForCustomer($customer),
            'next_visit' => $next === null ? null : [
                'date' => $next->route?->scheduled_date?->toDateString(),
                'agent' => $next->route?->agent?->firstName(),
                'pool' => $next->pool?->getAttribute('name'),
            ],
            'portal_status' => $customer->portalStatus(),
            'emails' => EmailPreferences::for($customer),
            'archived' => $customer->trashed(),
            'archived_on' => $customer->deleted_at?->toDateString(),
            'anonymized_on' => Tenant::localDate($customer->getAttribute('anonymized_at')),
            // What a permanent delete would remove — shown on the archived panel.
            'summary' => $archived ? [
                'pools' => $poolIds->count(),
                'visits' => ServiceVisit::query()->whereIn('pool_id', $poolIds)->count(),
                'invoices' => Invoice::query()->where('customer_id', $customer->id)->count(),
                'charges' => Charge::query()->where('customer_id', $customer->id)->count(),
            ] : null,
            'pools' => $customer->pools->map(fn ($pool) => [
                'id' => $pool->id,
                'name' => $pool->getAttribute('name'),
                'type' => $pool->getAttribute('type'),
                'volume' => $pool->getAttribute('volume_gallons'),
                'services' => ($services[$pool->id] ?? collect())->map(fn (ServiceSubscription $p): ?string => $p->serviceType?->name)->filter()->unique()->values()->all(),
            ])->all(),
            'recent_visits' => $visits->map(fn (ServiceVisit $visit) => [
                'id' => $visit->id,
                'pool' => $visit->pool?->getAttribute('name'),
                'service' => $visit->serviceSubscription?->serviceType?->name,
                'agent' => $visit->agent?->firstName(),
                'completed_on' => Tenant::localDate($visit->completed_at),
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
