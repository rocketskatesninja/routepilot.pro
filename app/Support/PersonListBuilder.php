<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unified People list — customers + agents in one paginated result.
 *
 * Replaces the legacy "query both tables, merge in memory, hand-paginate"
 * approach with a DB-level UNION the database paginates. Customers are
 * tenant-scoped by column; agents are users (role=agent) filtered to the
 * tenant explicitly (User is intentionally not globally tenant-scoped).
 * The People screen and its CSV/PDF export read the same filters and sort,
 * so an export is exactly the list on screen.
 */
class PersonListBuilder
{
    public function __construct(private BillingService $billing) {}

    /**
     * The list's tab, search and sort, off the request.
     *
     * @return array{type: 'all'|'customers'|'agents'|'archived', search: string, sort: 'name'|'type'|'email'|'phone', dir: 'asc'|'desc'}
     */
    public static function filters(Request $request): array
    {
        $type = (string) $request->string('type');
        $sort = (string) $request->string('sort');

        return [
            'type' => in_array($type, ['all', 'customers', 'agents', 'archived'], true) ? $type : 'all',
            'search' => trim((string) $request->string('search')),
            'sort' => in_array($sort, ['name', 'type', 'email', 'phone'], true) ? $sort : 'name',
            'dir' => strtolower((string) $request->string('dir')) === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * The whole filtered, sorted list as one query.
     *
     * @param  array{type: 'all'|'customers'|'agents'|'archived', search: string, sort: 'name'|'type'|'email'|'phone', dir: 'asc'|'desc'}  $filters
     */
    public function query(int $tenantId, array $filters): Builder
    {
        $search = $filters['search'];
        $query = match ($filters['type']) {
            'customers' => $this->customers($tenantId, $search),
            'agents' => $this->agents($tenantId, $search),
            'archived' => $this->customers($tenantId, $search, trashed: true),
            default => $this->customers($tenantId, $search)->unionAll($this->agents($tenantId, $search)),
        };

        $base = DB::query()->fromSub($query, 'people');
        $dir = $filters['dir'];
        match ($filters['sort']) {
            'email' => $base->orderBy('email', $dir)->orderBy('first_name'),
            'phone' => $base->orderBy('phone', $dir)->orderBy('first_name'),
            'type' => $base->orderBy('person_type', $dir)->orderBy('first_name')->orderBy('last_name'),
            default => $base->orderBy('first_name', $dir)->orderBy('last_name', $dir),
        };

        return $base;
    }

    /**
     * @param  array{type: 'all'|'customers'|'agents'|'archived', search: string, sort: 'name'|'type'|'email'|'phone', dir: 'asc'|'desc'}  $filters
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(int $tenantId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, \stdClass> $paginator */
        $paginator = $this->query($tenantId, $filters)->paginate($perPage);

        return $paginator;
    }

    /**
     * Fill in what the list shows for each customer row — balance, last visit
     * (in the company's time zone) and portal login — in a few batched queries.
     * Agent rows get nulls.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return Collection<int, \stdClass>
     */
    public function withDetails(Collection $rows): Collection
    {
        $customerIds = $rows->filter(fn (object $r): bool => $r->person_type === 'customer')
            ->map(fn (object $r): int => (int) $r->id)->values()->all();
        $balances = $this->billing->balancesFor($customerIds);
        $lastVisits = $this->lastVisitsFor($customerIds);
        $portal = $this->portalStatusesFor($customerIds);

        return $rows->map(function (object $r) use ($balances, $lastVisits, $portal): object {
            $isCustomer = $r->person_type === 'customer';
            $r->balance = $isCustomer ? ($balances[(int) $r->id] ?? 0.0) : null;
            $r->last_visit = $isCustomer ? ($lastVisits[(int) $r->id] ?? null) : null;
            $r->portal_status = $isCustomer ? ($portal[(int) $r->id] ?? 'none') : null;

            return $r;
        });
    }

    /** @return array{all: int, customers: int, agents: int, archived: int} */
    public function counts(int $tenantId, string $search = ''): array
    {
        $customers = $this->customers($tenantId, $search)->count();
        $agents = $this->agents($tenantId, $search)->count();
        $archived = $this->customers($tenantId, $search, trashed: true)->count();

        return ['all' => $customers + $agents, 'customers' => $customers, 'agents' => $agents, 'archived' => $archived];
    }

    private function customers(int $tenantId, string $search, bool $trashed = false): Builder
    {
        return DB::table('customers')
            ->when($trashed, fn (Builder $q) => $q->whereNotNull('deleted_at'), fn (Builder $q) => $q->whereNull('deleted_at'))
            ->where('tenant_id', $tenantId)
            ->when($search !== '', fn (Builder $q) => $this->whereName($q, $search))
            ->selectRaw("id, 'customer' as person_type, first_name, last_name, email, phone, photo_path as photo");
    }

    private function agents(int $tenantId, string $search): Builder
    {
        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role', 'agent')
            ->when($search !== '', fn (Builder $q) => $this->whereName($q, $search))
            ->selectRaw("id, 'agent' as person_type, first_name, last_name, email, phone, avatar_path as photo");
    }

    private function whereName(Builder $query, string $search): Builder
    {
        // Token-per-word match across first/last — portable (no SQL CONCAT).
        foreach (array_filter(explode(' ', trim($search))) as $token) {
            $like = '%'.$token.'%';
            $query->where(fn (Builder $q) => $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like));
        }

        return $query;
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
            ->map(fn ($d): ?string => $d !== null ? Tenant::localDate(Carbon::parse((string) $d, 'UTC')) : null)
            ->all();
    }

    /**
     * Portal-login state per customer — 'active', 'revoked' (login soft-deleted,
     * e.g. they deleted their own account) or 'none'. Two queries.
     *
     * @param  list<int>  $customerIds
     * @return array<int, string>
     */
    private function portalStatusesFor(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $userIdByCustomer = Customer::withTrashed()
            ->whereIn('id', $customerIds)
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id');
        if ($userIdByCustomer->isEmpty()) {
            return [];
        }

        $deletedByUserId = User::withTrashed()
            ->whereIn('id', $userIdByCustomer->values()->all())
            ->pluck('deleted_at', 'id');

        $out = [];
        foreach ($userIdByCustomer as $customerId => $userId) {
            if (! $deletedByUserId->has($userId)) {
                continue; // dangling reference → treated as no portal ('none')
            }
            $out[(int) $customerId] = $deletedByUserId[$userId] === null ? 'active' : 'revoked';
        }

        return $out;
    }
}
