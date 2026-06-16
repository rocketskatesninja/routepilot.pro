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

/** Soft-delete a customer and backdate the deletion by $days. */
function erasedCustomer(Tenant $tenant, int $days): Customer
{
    $customer = Customer::factory()->for($tenant)->create();
    $customer->delete();
    DB::table('customers')->where('id', $customer->id)->update(['deleted_at' => now()->subDays($days)]);

    return $customer;
}

test('a customer past the retention window is hard-deleted with their PII cascade', function () {
    Storage::fake('public');

    $customer = erasedCustomer($this->tenant, 400);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create([
        'agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now(),
    ]);
    Storage::disk('public')->put('visits/p.jpg', 'binary');
    $photo = VisitPhoto::query()->create(['service_visit_id' => $visit->id, 'photo_path' => 'visits/p.jpg']);

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(Customer::withTrashed()->find($customer->id))->toBeNull()
        ->and(Pool::withTrashed()->find($pool->id))->toBeNull()
        ->and(ServiceVisit::query()->find($visit->id))->toBeNull()
        ->and(VisitPhoto::query()->find($photo->id))->toBeNull()
        ->and(Storage::disk('public')->exists('visits/p.jpg'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'retention.purged')->count())->toBe(1);
});

test('a recently soft-deleted customer is left alone', function () {
    $customer = erasedCustomer($this->tenant, 10);

    $this->artisan('app:purge-retention')->assertSuccessful();

    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull();
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

test('a dry run deletes nothing', function () {
    $customer = erasedCustomer($this->tenant, 400);

    $this->artisan('app:purge-retention --dry')->assertSuccessful();

    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'retention.purged')->count())->toBe(0);
});
