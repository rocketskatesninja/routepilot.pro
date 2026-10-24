<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Actions\RecordPayment;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Services\BillingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting a visit report undoes what the visit did: its charge, the chemicals
 * it took from stock, its photos, and its finished route stop. Office admins
 * only.
 */
beforeEach(function () {
    Mail::fake();
    Storage::fake('public');
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create(['price' => 50]))
        ->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
    $this->chlorine = ChemicalInventory::create(['chemical_name' => 'Liquid Chlorine', 'unit' => 'oz', 'current_stock' => 100, 'is_active' => true]);
});

/** The agent finishes the plan's stop on the given day from the field app, with a treatment and a photo. */
function finishedStop(string $date, string $key = ''): RouteStop
{
    $route = Route::forDay(test()->tenant->id, test()->agent->id, $date);
    $stop = RouteStop::factory()->for($route)->for(test()->pool)->create(['service_subscription_id' => test()->plan->id, 'status' => 'pending']);

    test()->actingAs(test()->agent)->post("/api/field/visits/{$stop->id}/complete", [
        'free_chlorine' => 2.5, 'ph' => 7.5,
        'tasks' => [['name' => 'Skim', 'done' => true]],
        'treatments' => [['chemical_id' => test()->chlorine->id, 'amount' => 32]],
        'photos' => [UploadedFile::fake()->image('pool.jpg')],
        'idempotency_key' => $key ?: null,
    ])->assertOk();

    return $stop->fresh();
}

function reportBalance(): float
{
    return app(BillingService::class)->outstandingForCustomer(test()->customer);
}

test('deleting a report undoes what the visit did', function () {
    $stop = finishedStop($this->tenant->today()->toDateString());
    $visit = ServiceVisit::query()->sole();
    $photo = VisitPhoto::query()->sole()->photo_path;
    expect((float) $this->chlorine->fresh()->current_stock)->toBe(68.0)
        ->and(reportBalance())->toBe(50.0)
        ->and($stop->status)->toBe('completed');
    Storage::disk('public')->assertExists($photo);

    $this->actingAs($this->admin)->delete("/reports/{$visit->id}")->assertSessionHas('success');

    expect(ServiceVisit::query()->exists())->toBeFalse()
        ->and(VisitPhoto::query()->exists())->toBeFalse()
        ->and((float) $this->chlorine->fresh()->current_stock)->toBe(100.0)   // back on the shelf
        ->and(reportBalance())->toBe(0.0)                                           // no longer billed
        ->and(Charge::query()->sole()->void_reason)->toBe('Visit report deleted')
        ->and($stop->fresh()->status)->toBe('pending')                        // due again today
        ->and($stop->fresh()->completed_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'report.deleted')->where('model_id', $visit->id)->exists())->toBeTrue();
    Storage::disk('public')->assertMissing($photo);
});

test('deleting a report that used more than was on the shelf puts back only what came off it', function () {
    $this->chlorine->update(['current_stock' => 10]); // the visit uses 32 oz

    finishedStop($this->tenant->today()->toDateString());
    expect((float) $this->chlorine->fresh()->current_stock)->toBe(0.0);

    $this->actingAs($this->admin)->delete('/reports/'.ServiceVisit::query()->sole()->id);

    expect((float) $this->chlorine->fresh()->current_stock)->toBe(10.0); // not 32
});

test('a report from a past day reopens its stop as missed', function () {
    $stop = finishedStop($this->tenant->today()->subDays(3)->toDateString());

    $this->actingAs($this->admin)->delete('/reports/'.ServiceVisit::query()->sole()->id);

    expect($stop->fresh()->status)->toBe('missed');
});

test('deleting a billed and paid report updates the invoice and leaves the money as credit', function () {
    finishedStop($this->tenant->today()->toDateString());
    $invoice = app(GenerateInvoice::class)->handle($this->customer);
    app(RecordPayment::class)->handle($this->customer, 'cash', $this->admin->id);
    expect($invoice?->fresh()?->status)->toBe('paid');

    $this->actingAs($this->admin)->delete('/reports/'.ServiceVisit::query()->sole()->id);

    expect((float) $invoice?->fresh()?->total)->toBe(0.0)
        ->and(reportBalance())->toBe(-50.0); // $50 in credit
});

test('the report panel tells the admin what deleting it would cancel', function () {
    finishedStop($this->tenant->today()->toDateString());
    $invoice = app(GenerateInvoice::class)->handle($this->customer);
    $visit = ServiceVisit::query()->sole();

    $this->actingAs($this->admin)->get("/reports?selected={$visit->id}")
        ->assertInertia(fn ($page) => $page
            ->where('selected.can_delete', true)
            ->where('selected.charge.amount', 50)
            ->where('selected.charge.invoice', $invoice?->number));
});

test('agents can\'t delete reports, not even their own', function () {
    finishedStop($this->tenant->today()->toDateString());
    $visit = ServiceVisit::query()->sole();

    $this->actingAs($this->agent)->delete("/reports/{$visit->id}")->assertForbidden();
    $this->actingAs($this->agent)->get("/reports?selected={$visit->id}")
        ->assertInertia(fn ($page) => $page->where('selected.can_delete', false));

    expect($visit->fresh())->not->toBeNull();
});

test('another company\'s report can\'t be deleted', function () {
    finishedStop($this->tenant->today()->toDateString());
    $visit = ServiceVisit::query()->sole();
    $otherAdmin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($otherAdmin)->delete("/reports/{$visit->id}")->assertNotFound();

    expect($visit->fresh())->not->toBeNull();
});

test('a phone replaying its original sync doesn\'t bring a deleted report back', function () {
    $stop = finishedStop($this->tenant->today()->toDateString(), 'sync-key-1');
    $this->actingAs($this->admin)->delete('/reports/'.ServiceVisit::query()->sole()->id);

    $this->actingAs($this->agent)->post("/api/field/visits/{$stop->id}/complete", [
        'treatments' => [['chemical_id' => test()->chlorine->id, 'amount' => 32]],
        'idempotency_key' => 'sync-key-1',
    ])->assertOk()->assertJsonPath('idempotent', true);

    expect(ServiceVisit::query()->exists())->toBeFalse()
        ->and((float) $this->chlorine->fresh()->current_stock)->toBe(100.0);
});
