<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceLocation;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LegacyImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Insert a model's generated attributes (minus id) into the legacy connection. */
function legacyInsert(string $table, array $attrs): int
{
    unset($attrs['id']);

    return DB::connection('legacy')->table($table)->insertGetId($attrs);
}

beforeEach(function () {
    // A separate in-memory DB stands in for the restored old-DB dump.
    config()->set('database.connections.legacy', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]);
    DB::purge('legacy');
    Artisan::call('migrate', ['--database' => 'legacy', '--force' => true]);

    // Seed a tiny "old Glynn" tenant: admin + customer + pool + location + visit.
    $this->oldTenant = legacyInsert('tenants', Tenant::factory()->make(['slug' => 'oldgpc', 'name' => 'Old GPC'])->getAttributes());

    $this->oldAdmin = legacyInsert('users', array_merge(
        User::factory()->make()->getAttributes(),
        ['tenant_id' => $this->oldTenant, 'role' => 'tenant_admin', 'is_active' => true, 'email' => 'jonathan@gpc.test'],
    ));

    $this->oldCustomer = legacyInsert('customers', array_merge(
        Customer::factory()->make()->getAttributes(),
        ['tenant_id' => $this->oldTenant, 'user_id' => null, 'first_name' => 'Jan'],
    ));

    $this->oldPool = legacyInsert('pools', array_merge(
        Pool::factory()->make()->getAttributes(),
        ['tenant_id' => $this->oldTenant, 'customer_id' => $this->oldCustomer, 'name' => 'Jan Pool'],
    ));

    legacyInsert('service_locations', array_merge(
        ServiceLocation::factory()->make()->getAttributes(),
        ['pool_id' => $this->oldPool, 'lat' => 31.205, 'lng' => -81.515],
    ));

    legacyInsert('service_visits', array_merge(
        ServiceVisit::factory()->make()->getAttributes(),
        ['tenant_id' => $this->oldTenant, 'pool_id' => $this->oldPool, 'agent_id' => $this->oldAdmin, 'route_stop_id' => null, 'status' => 'completed'],
    ));
});

function importOpts(array $over = []): array
{
    return array_merge([
        'source' => 'legacy', 'old_slug' => 'oldgpc', 'slug' => 'gpc', 'name' => 'Acme Pool Co', 'dry' => false, 'fresh' => false,
    ], $over);
}

test('it imports the legacy tenant into a fresh tenant with remapped foreign keys', function () {
    $result = app(LegacyImporter::class)->run(importOpts());

    $tenant = Tenant::query()->where('slug', 'gpc')->firstOrFail();
    expect($tenant->name)->toBe('Acme Pool Co')
        ->and($result['customers'])->toBe(1)
        ->and($result['pools'])->toBe(1)
        ->and($result['visits'])->toBe(1);

    app()->instance('tenant_id', $tenant->id);
    $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $pool = Pool::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $admin = User::query()->where('tenant_id', $tenant->id)->where('role', 'tenant_admin')->firstOrFail();

    expect($customer->first_name)->toBe('Jan')
        ->and($pool->customer_id)->toBe($customer->id) // remapped
        ->and($pool->serviceLocation?->lat)->toBe(31.205) // coords preserved
        ->and(ServiceVisit::query()->where('agent_id', $admin->id)->count())->toBe(1); // agent remapped
});

test('it keeps the legacy password hash so staff log in as before', function () {
    $hash = (string) DB::connection('legacy')->table('users')->where('id', $this->oldAdmin)->value('password');

    app(LegacyImporter::class)->run(importOpts());

    $admin = User::query()->where('email', 'jonathan@gpc.test')->firstOrFail();
    expect($admin->getAttribute('password'))->toBe($hash);
});

test('a dry run reports counts but writes nothing', function () {
    $result = app(LegacyImporter::class)->run(importOpts(['dry' => true]));

    expect($result['dry'])->toBeTrue()
        ->and($result['customers'])->toBe(1)
        ->and(Tenant::query()->where('slug', 'gpc')->exists())->toBeFalse();
});

test('re-running is a no-op once imported, unless --fresh', function () {
    app(LegacyImporter::class)->run(importOpts());
    $again = app(LegacyImporter::class)->run(importOpts());
    expect($again['skipped'] ?? false)->toBeTrue();

    $fresh = app(LegacyImporter::class)->run(importOpts(['fresh' => true]));
    expect($fresh['customers'])->toBe(1)
        ->and(Tenant::query()->where('slug', 'gpc')->count())->toBe(1); // not duplicated
});
