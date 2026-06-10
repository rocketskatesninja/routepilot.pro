<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
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
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(int $tenantId, string $type = 'all', string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        $query = match ($type) {
            'customers' => $this->customers($tenantId, $search),
            'agents' => $this->agents($tenantId, $search),
            default => $this->customers($tenantId, $search)->unionAll($this->agents($tenantId, $search)),
        };

        /** @var LengthAwarePaginator<int, \stdClass> $paginator */
        $paginator = DB::query()
            ->fromSub($query, 'people')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($perPage);

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
            ->selectRaw("id, 'customer' as person_type, first_name, last_name, email, phone");
    }

    private function agents(int $tenantId, string $search): Builder
    {
        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('role', 'agent')
            ->when($search !== '', fn (Builder $q) => $this->whereName($q, $search))
            ->selectRaw("id, 'agent' as person_type, first_name, last_name, email, phone");
    }

    private function whereName(Builder $query, string $search): Builder
    {
        return $query->whereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) LIKE ?", ['%'.$search.'%']);
    }
}
