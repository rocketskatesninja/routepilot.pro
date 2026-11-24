<?php

declare(strict_types=1);

use App\Http\Requests\CompleteVisitRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

test('binding a company binds both the model and its id', function () {
    $tenant = Tenant::factory()->create(['timezone' => 'America/Los_Angeles']);

    $tenant->bind();

    expect(app('tenant_id'))->toBe($tenant->id)
        ->and(app('tenant')->is($tenant))->toBeTrue()
        ->and(Tenant::localToday()->getTimezone()->getName())->toBe('America/Los_Angeles');
});

test('eachActive gives every company its own day and leaves nothing bound', function () {
    // 03:30 UTC on the 2nd is still the 1st in New York and Los Angeles, already the 2nd in Berlin.
    Carbon::setTestNow(Carbon::parse('2026-07-02 03:30:00', 'UTC'));
    $east = Tenant::factory()->create(['timezone' => 'America/New_York']);
    $berlin = Tenant::factory()->create(['timezone' => 'Europe/Berlin']);
    Tenant::factory()->create(['timezone' => 'America/Chicago', 'status' => 'suspended']);

    $seen = [];
    Tenant::eachActive(function (Tenant $t) use (&$seen): void {
        $seen[$t->id] = [app('tenant_id'), Tenant::localToday()->toDateString()];
    });

    expect($seen)->toBe([
        $east->id => [$east->id, '2026-07-01'],
        $berlin->id => [$berlin->id, '2026-07-02'],
    ])->and(app()->has('tenant'))->toBeFalse()
        ->and(app()->has('tenant_id'))->toBeFalse();

    Carbon::setTestNow();
});

test('eachActive puts back what was bound before, even when a pass throws', function () {
    $mine = Tenant::factory()->create();
    Tenant::factory()->create();
    $mine->bind();

    expect(fn () => Tenant::eachActive(fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);

    expect(app('tenant_id'))->toBe($mine->id)->and(app('tenant')->is($mine))->toBeTrue();
});

test('eachActive can keep to companies that bill their customers', function () {
    $billing = Tenant::factory()->create(['customer_billing' => true]);
    Tenant::factory()->create(['customer_billing' => false]);

    $ids = [];
    Tenant::eachActive(function (Tenant $t) use (&$ids): void {
        $ids[] = $t->id;
    }, billingOnly: true);

    expect($ids)->toBe([$billing->id]);
});

test('the field app and the office accept the same report limits', function () {
    $field = (new CompleteVisitRequest)->rules();
    $office = (new UpdateReportRequest)->rules();

    foreach (['free_chlorine', 'total_chlorine', 'ph', 'alkalinity', 'calcium_hardness', 'cyanuric_acid', 'salt', 'water_temperature'] as $reading) {
        expect($office['reading.'.$reading])->toBe($field[$reading]);
    }
    foreach (['notes', 'office_notes', 'tasks.*.name', 'treatments.*.name', 'treatments.*.amount', 'treatments.*.unit', 'parts.*.amount'] as $key) {
        expect($office[$key])->toBe($field[$key]);
    }
});

test('a reading the field app accepts can be saved again from the office', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    $admin = User::factory()->for($tenant)->create();
    $pool = Pool::factory()->for($tenant)->for(Customer::factory()->for($tenant)->create())->create();
    $visit = ServiceVisit::factory()->for($tenant)->for($pool)->create(['agent_id' => $admin->id, 'status' => 'completed', 'completed_at' => now()]);

    // Salt-pool numbers the field app has always taken; the office form used to cap salt at 10,000 and CYA at 500.
    $this->actingAs($admin)->patch("/reports/{$visit->id}", [
        'completed_on' => now()->toDateString(),
        'reading' => ['salt' => 15000, 'cyanuric_acid' => 600],
    ])->assertSessionHasNoErrors();

    expect((float) $visit->chemicalReading()->first()?->salt)->toBe(15000.0);
});
