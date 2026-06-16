<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * One-time cutover: copy a single tenant's domain data from a restored dump of
 * the old RoutePilot DB (the `legacy` connection) into a fresh tenant on the
 * live DB. The old schema matches the current one, so each table is a generic
 * column-intersected copy with the foreign keys remapped old→new id.
 *
 * Tenant-level idempotent: re-running is a no-op once the target tenant has
 * customers, unless $fresh wipes it first. The whole run is one transaction —
 * a dry run rolls it back so the counts are real but nothing persists.
 */
class LegacyImporter
{
    /** Build old→new id maps as tables are copied. */
    private ConnectionInterface $old;

    /**
     * @param  array{source: string, slug: string, name: string, old_slug: string, dry: bool, fresh: bool}  $opts
     * @return array<string, int|bool|string>
     */
    public function run(array $opts): array
    {
        $this->old = DB::connection($opts['source']);

        $oldTenant = $this->old->table('tenants')->where('slug', $opts['old_slug'])->first();
        if ($oldTenant === null) {
            throw new RuntimeException("Source tenant '{$opts['old_slug']}' not found on the legacy connection.");
        }

        $existing = Tenant::query()->where('slug', $opts['slug'])->first();
        if ($existing !== null && ! $opts['fresh'] && Customer::query()->where('tenant_id', $existing->id)->exists()) {
            return ['skipped' => true, 'reason' => 'already imported', 'tenant_id' => $existing->id];
        }

        DB::beginTransaction();
        try {
            if ($existing !== null && $opts['fresh']) {
                // users.tenant_id is nullOnDelete (super-admins are tenant-less),
                // so it won't cascade — remove the tenant's users explicitly first.
                User::query()->where('tenant_id', $existing->id)->forceDelete();
                $existing->forceDelete(); // cascade clears the rest of its data
            }

            $counts = $this->import($oldTenant, $opts);

            if ($opts['dry']) {
                DB::rollBack();

                return ['dry' => true] + $counts;
            }
            DB::commit();

            return $counts;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  array{source: string, slug: string, name: string, old_slug: string, dry: bool, fresh: bool}  $opts
     * @return array<string, int|string>
     */
    private function import(object $oldTenant, array $opts): array
    {
        $oldId = (int) $oldTenant->id;

        // The tenant itself, with the new slug/name.
        $tenantMap = $this->copy('tenants', fn ($q) => $q->where('id', $oldId), [
            'slug' => fn () => $opts['slug'],
            'name' => fn () => $opts['name'],
        ]);
        $tenantId = $tenantMap[$oldId];

        $byTenant = fn ($q) => $q->where('tenant_id', $oldId);
        $reTenant = ['tenant_id' => fn () => $tenantId];

        $tableExists = static fn (string $t): bool => Schema::hasTable($t);

        if ($tableExists('tenant_settings')) {
            $this->copy('tenant_settings', $byTenant, $reTenant);
        }

        $users = $this->copy('users', $byTenant, $reTenant);
        $types = $this->copy('service_types', $byTenant, $reTenant);

        $customers = $this->copy('customers', $byTenant, $reTenant + [
            'user_id' => fn ($v) => $v ? ($users[$v] ?? null) : null,
        ]);

        $pools = $this->copy('pools', $byTenant, $reTenant + [
            'customer_id' => fn ($v) => $customers[$v] ?? null,
        ]);

        $poolIds = array_keys($pools);
        $this->copy('service_locations', fn ($q) => $q->whereIn('pool_id', $poolIds ?: [0]), [
            'pool_id' => fn ($v) => $pools[$v] ?? null,
        ]);

        $subs = $this->copy('service_subscriptions', $byTenant, $reTenant + [
            'pool_id' => fn ($v) => $pools[$v] ?? null,
            'service_type_id' => fn ($v) => $types[$v] ?? null,
            'assigned_agent_id' => fn ($v) => $v ? ($users[$v] ?? null) : null,
        ]);

        $visits = $this->copy('service_visits', $byTenant, $reTenant + [
            'pool_id' => fn ($v) => $pools[$v] ?? null,
            'agent_id' => fn ($v) => $users[$v] ?? null,
            'service_subscription_id' => fn ($v) => $v ? ($subs[$v] ?? null) : null,
            'route_stop_id' => fn () => null, // routes are ephemeral — not ported
        ]);

        $visitIds = array_keys($visits) ?: [0];
        $readings = $this->copy('chemical_readings', fn ($q) => $q->whereIn('service_visit_id', $visitIds), [
            'service_visit_id' => fn ($v) => $visits[$v] ?? null,
        ]);
        $treatments = $this->copy('chemical_treatments', fn ($q) => $q->whereIn('service_visit_id', $visitIds), [
            'service_visit_id' => fn ($v) => $visits[$v] ?? null,
            'chemical_inventory_id' => fn () => null, // inventory not ported
        ]);

        $charges = $this->copy('manual_charges', $byTenant, $reTenant + [
            'customer_id' => fn ($v) => $customers[$v] ?? null,
            'created_by' => fn ($v) => $v ? ($users[$v] ?? null) : null,
        ]);

        return [
            'tenant_id' => $tenantId,
            'users' => count($users),
            'service_types' => count($types),
            'customers' => count($customers),
            'pools' => count($pools),
            'subscriptions' => count($subs),
            'visits' => count($visits),
            'readings' => count($readings),
            'treatments' => count($treatments),
            'charges' => count($charges),
        ];
    }

    /**
     * Copy rows from the legacy table into the target, dropping the id (auto
     * re-assigned), applying $remaps (col => fn(oldValue, row) | const), and
     * keeping only columns the target table actually has. Returns old→new ids.
     *
     * @param  array<string, Closure|mixed>  $remaps
     * @return array<int, int>
     */
    private function copy(string $table, Closure $filter, array $remaps): array
    {
        $targetCols = array_flip(Schema::getColumnListing($table));
        $rows = $filter($this->old->table($table))->get();
        $map = [];

        foreach ($rows as $row) {
            $data = (array) $row;
            $oldId = (int) $data['id'];
            unset($data['id']);

            foreach ($remaps as $col => $fn) {
                $data[$col] = $fn instanceof Closure ? $fn($data[$col] ?? null, $data) : $fn;
            }

            $data = array_intersect_key($data, $targetCols);
            $map[$oldId] = (int) DB::table($table)->insertGetId($data);
        }

        return $map;
    }
}
