<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

/** Soft-delete (archive) a customer and backdate the archiving by $days. */
function archivedCustomer(Tenant $tenant, int $days): Customer
{
    $customer = Customer::factory()->for($tenant)->create();
    $customer->delete();
    DB::table('customers')->where('id', $customer->id)->update(['deleted_at' => now()->subDays($days)]);

    return $customer;
}

/** A read notification for the agent, $ageDays old. */
function oldReadNotification(User $user, int $ageDays): void
{
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\Test',
        'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => '{}',
        'read_at' => now()->subDays($ageDays), 'created_at' => now()->subDays($ageDays), 'updated_at' => now()->subDays($ageDays),
    ]);
}

test('an archived customer is never deleted by the clean-up, however long ago', function () {
    Storage::fake('public');
    $customer = archivedCustomer($this->tenant, 2000);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create([
        'agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now()->subYears(5),
    ]);
    Storage::disk('public')->put('visits/p.jpg', 'binary');
    VisitPhoto::query()->create(['service_visit_id' => $visit->id, 'photo_path' => 'visits/p.jpg']);
    $charge = manualCharge($customer, 50);

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull()
        ->and(ServiceVisit::query()->find($visit->id))->not->toBeNull()
        ->and($charge->fresh())->not->toBeNull()
        ->and(Storage::disk('public')->exists('visits/p.jpg'))->toBeTrue();
});

test('a run that prunes something is audited', function () {
    oldReadNotification($this->agent, 100);

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(AuditLog::query()->where('action', 'retention.purged')->count())->toBe(1);
});

test('old read notifications are pruned, recent and unread are kept', function () {
    $insert = function (?string $readAt, int $ageDays): void {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Test',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->agent->id,
            'data' => '{}',
            'read_at' => $readAt,
            'created_at' => now()->subDays($ageDays),
            'updated_at' => now()->subDays($ageDays),
        ]);
    };
    $insert(now()->subDays(100)->toDateTimeString(), 100); // old + read → pruned
    $insert(now()->toDateTimeString(), 10);                // recent + read → kept
    $insert(null, 200);                                    // old + unread → kept

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(DB::table('notifications')->count())->toBe(2);
});

test('stale agent locations are pruned', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    DB::table('agent_locations')->insert([
        'tenant_id' => $this->tenant->id, 'agent_id' => $agent->id, 'lat' => 31.2, 'lng' => -81.5,
        'recorded_at' => now()->subDays(30), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(DB::table('agent_locations')->count())->toBe(0);
});

test('a dry run deletes nothing', function () {
    oldReadNotification($this->agent, 100);

    $this->artisan('app:purge-retention --dry')->assertSuccessful();

    expect(DB::table('notifications')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'retention.purged')->count())->toBe(0);
});
