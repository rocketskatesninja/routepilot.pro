<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Unified People list — customers + agents in one paginated result.
 *
 * Replaces the legacy "query both tables, merge in memory, hand-paginate"
 * approach with a DB-level UNION the database paginates. Customers are
 * tenant-scoped by column; agents are users (role=agent) filtered to the
 * tenant explicitly (User is intentionally not globally tenant-scoped).
 */
class PersonListBuilder
{
    /**
     * @param  'all'|'customers'|'agents'  $type
     * @param  'name'|'type'|'email'|'phone'  $sortKey
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(int $tenantId, string $type = 'all', string $search = '', int $perPage = 20, string $sortKey = 'name', string $sortDir = 'asc'): LengthAwarePaginator
    {
        $query = match ($type) {
            'customers' => $this->customers($tenantId, $search),
            'agents' => $this->agents($tenantId, $search),
            default => $this->customers($tenantId, $search)->unionAll($this->agents($tenantId, $search)),
        };

        $base = DB::query()->fromSub($query, 'people');
        $dir = $sortDir === 'desc' ? 'desc' : 'asc';
        match ($sortKey) {
            'email' => $base->orderBy('email', $dir)->orderBy('first_name'),
            'phone' => $base->orderBy('phone', $dir)->orderBy('first_name'),
            'type' => $base->orderBy('person_type', $dir)->orderBy('first_name')->orderBy('last_name'),
            default => $base->orderBy('first_name', $dir)->orderBy('last_name', $dir),
        };

        /** @var LengthAwarePaginator<int, \stdClass> $paginator */
        $paginator = $base->paginate($perPage);

        return $paginator;
    }

    /** @return array{all: int, customers: int, agents: int} */
    public function counts(int $tenantId, string $search = ''): array
    {
        $customers = $this->customers($tenantId, $search)->count();
        $agents = $this->agents($tenantId, $search)->count();

        return ['all' => $customers + $agents, 'customers' => $customers, 'agents' => $agents];
    }

    private function customers(int $tenantId, string $search): Builder
    {
        return DB::table('customers')
            ->whereNull('deleted_at')
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
}
