<?php

declare(strict_types=1);

use App\Actions\RegisterTenant;
use App\Actions\SaveDashboardLayout;
use App\Dashboard\Advisories;
use App\Dashboard\DashboardWidgets;
use App\Mail\VisitRecapMail;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\Tools\LookupInventory;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A company can stop counting its stock here. Techs still record the chemicals
 * they add, from a plain list; stock levels, low-stock alerts and repair parts go.
 */

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'homeowner@gmail.com']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create(['price' => 50]))
        ->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
    $this->acid = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Muriatic Acid', 'unit' => 'gal', 'current_stock' => 20, 'reorder_threshold' => 5, 'sell_price' => 4.50]);
    $this->part = ChemicalInventory::factory()->for($this->tenant)->create(['chemical_name' => 'Pump lid O-ring', 'kind' => 'part', 'unit' => 'unit', 'current_stock' => 6, 'sell_price' => 12]);
});

function trackingOff(Tenant $tenant): void
{
    $tenant->forceFill(['inventory_tracking' => false])->save();
}

/** A user read fresh, so their company's switches are current. */
function freshly(User $user): User
{
    return User::query()->findOrFail($user->id);
}

/** The tech finishes a new stop on this test's pool. */
function finishStop(array $fields = []): ServiceVisit
{
    $route = Route::forDay(test()->tenant->id, test()->agent->id, test()->tenant->today()->toDateString());
    $stop = RouteStop::factory()->for($route)->for(test()->pool)->create(['service_subscription_id' => test()->plan->id, 'status' => 'pending']);
    test()->actingAs(freshly(test()->agent))->post("/api/field/visits/{$stop->id}/complete", ['ph' => 7.4, ...$fields])->assertOk();

    return ServiceVisit::query()->where('route_stop_id', $stop->id)->sole();
}

test('every company starts with it on', function () {
    expect($this->tenant->inventory_tracking)->toBeTrue()
        ->and($this->tenant->fresh()?->inventory_tracking)->toBeTrue();

    $user = app(RegisterTenant::class)(['company' => 'New Pools', 'first_name' => 'Ana', 'email' => 'ana@homeowner.co', 'password' => 'a-long-password']);

    expect($user->tenant?->inventory_tracking)->toBeTrue();
});

test('an admin can switch it, each change is audited, and billing is left alone', function () {
    $this->actingAs($this->admin)->patch('/company/features', ['inventory_tracking' => false])->assertRedirect()->assertSessionHas('success', 'Inventory tracking is off.');

    expect($this->tenant->fresh()?->inventory_tracking)->toBeFalse()
        ->and($this->tenant->fresh()?->customer_billing)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'company.inventory_tracking')->count())->toBe(1);

    $this->actingAs($this->admin)->patch('/company/features', ['inventory_tracking' => false])->assertRedirect();
    expect(AuditLog::query()->where('action', 'company.inventory_tracking')->count())->toBe(1);

    $this->actingAs($this->admin)->patch('/company/features', ['customer_billing' => false])->assertRedirect();
    expect($this->tenant->fresh()?->customer_billing)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'company.inventory_tracking')->count())->toBe(1);
});

test('only the company’s admin can change it, and only for their own company', function () {
    $this->actingAs($this->agent)->patch('/company/features', ['inventory_tracking' => false])->assertForbidden();
    expect($this->tenant->fresh()?->inventory_tracking)->toBeTrue();

    $other = Tenant::factory()->create();
    $this->actingAs($this->admin)->patch('/company/features', ['inventory_tracking' => false])->assertRedirect();
    expect($other->fresh()?->inventory_tracking)->toBeTrue();
});

test('it has to be a yes or no', function () {
    $this->actingAs($this->admin)->patch('/company/features', ['inventory_tracking' => 'maybe'])->assertSessionHasErrors('inventory_tracking');
    expect($this->tenant->fresh()?->inventory_tracking)->toBeTrue();
});

test('with it on, a visit takes what it used off the shelf', function () {
    finishStop(['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]], 'parts' => [['part_id' => $this->part->id, 'amount' => 1]]]);

    expect((float) $this->acid->fresh()?->current_stock)->toBe(18.0)
        ->and((float) $this->part->fresh()?->current_stock)->toBe(5.0);
});

test('with it off, a visit records its chemicals and moves nothing', function () {
    trackingOff($this->tenant);

    $visit = finishStop(['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]]]);

    expect($visit->treatments()->pluck('chemical_name')->all())->toBe(['Muriatic Acid'])
        ->and((float) $this->acid->fresh()?->current_stock)->toBe(20.0)
        ->and(InventoryTransaction::query()->where('service_visit_id', $visit->id)->count())->toBe(0);
});

test('with it off, a part sent by an old copy of the app is ignored', function () {
    trackingOff($this->tenant);

    $visit = finishStop(['parts' => [['part_id' => $this->part->id, 'amount' => 1]]]);

    expect($visit->treatments()->count())->toBe(0)
        ->and((float) $this->part->fresh()?->current_stock)->toBe(6.0);
});

test('editing an earlier visit with it off keeps its parts and leaves the shelf alone', function () {
    $visit = finishStop(['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]], 'parts' => [['part_id' => $this->part->id, 'amount' => 1]]]);
    trackingOff($this->tenant);

    $this->actingAs(freshly($this->agent))->post("/api/field/visits/{$visit->route_stop_id}/complete", ['ph' => 7.5, 'treatments' => [['chemical_id' => $this->acid->id, 'amount' => 3]], 'parts' => []])->assertOk();

    expect($visit->treatments()->pluck('chemical_name')->sort()->values()->all())->toBe(['Muriatic Acid', 'Pump lid O-ring'])
        ->and((float) $this->acid->fresh()?->current_stock)->toBe(18.0)
        ->and((float) $this->part->fresh()?->current_stock)->toBe(5.0);
});

test('with it off and billing on, chemicals can still be billed per visit', function () {
    trackingOff($this->tenant);
    $this->customer->update(['bill_chemicals' => true]);

    finishStop(['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]]]);

    expect((float) Charge::query()->where('kind', 'chemicals')->sole()->amount)->toBe(9.0);
});

test('the visit email keeps its chemicals', function () {
    trackingOff($this->tenant);

    finishStop(['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]]]);

    Mail::assertQueued(VisitRecapMail::class, function (VisitRecapMail $mail): bool {
        $html = $mail->render();

        return str_contains($html, 'Chemicals added') && str_contains($html, 'Muriatic Acid') && ! str_contains($html, 'Parts replaced');
    });
});

test('the field app’s list drops parts and keeps chemicals', function () {
    $names = fn (): array => collect($this->actingAs(freshly($this->agent))->getJson('/api/field/today')->assertOk()->json('inventory'))->pluck('name')->all();

    expect($names())->toContain('Muriatic Acid', 'Pump lid O-ring');

    trackingOff($this->tenant);

    expect($names())->toContain('Muriatic Acid')->not->toContain('Pump lid O-ring');
});

test('the office report editor has no parts to pick', function () {
    $visit = finishStop();
    trackingOff($this->tenant);

    $this->actingAs(freshly($this->admin))->get('/reports?selected='.$visit->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('parts', 0)->has('chemicals', 1));
});

test('with it off, the page lists only chemicals, however it’s reached', function () {
    trackingOff($this->tenant);
    $admin = freshly($this->admin);

    $this->actingAs($admin)->get('/inventory?type=parts')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.name', 'Muriatic Acid')->where('filters.type', 'chemicals'));

    $this->actingAs($admin)->get('/inventory?selected='.$this->part->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selected', null)->where('filters.type', 'chemicals'));
});

test('with it off, chemicals can still be added, renamed and archived', function () {
    trackingOff($this->tenant);
    $admin = freshly($this->admin);

    $this->actingAs($admin)->post('/inventory', ['chemical_name' => 'Algaecide', 'unit' => 'gal', 'current_stock' => 0])->assertRedirect();
    $added = ChemicalInventory::query()->where('chemical_name', 'Algaecide')->sole();
    expect($added->kind)->toBe('chemical');

    $this->actingAs($admin)->patch('/inventory/'.$added->id, ['chemical_name' => 'Algaecide 60', 'unit' => 'gal', 'sell_price' => 8])->assertRedirect();
    expect($added->fresh()?->chemical_name)->toBe('Algaecide 60')
        ->and((float) $added->fresh()?->sell_price)->toBe(8.0);

    $this->actingAs($admin)->post('/inventory/'.$added->id.'/archive')->assertRedirect();
    expect($added->fresh()?->is_active)->toBeFalse();
});

test('with it off, stock can’t be adjusted', function () {
    trackingOff($this->tenant);

    $this->actingAs(freshly($this->admin))->post('/inventory/'.$this->acid->id.'/adjust', ['type' => 'restock', 'quantity' => 5])->assertNotFound();

    expect((float) $this->acid->fresh()?->current_stock)->toBe(20.0);
});

test('turning it back on finds the counts and parts as they were', function () {
    trackingOff($this->tenant);
    finishStop(['treatments' => [['chemical_id' => $this->acid->id, 'amount' => 2]]]);

    $this->tenant->forceFill(['inventory_tracking' => true])->save();

    expect((float) $this->acid->fresh()?->current_stock)->toBe(20.0)
        ->and($this->part->fresh()?->is_active)->toBeTrue();
    $this->actingAs(freshly($this->admin))->get('/inventory?type=parts')->assertInertia(fn (Assert $page) => $page->where('items.data.0.name', 'Pump lid O-ring'));
});

test('the running-low advisory goes with it', function () {
    $this->acid->forceFill(['current_stock' => 1])->save();
    $low = fn (): bool => collect(Advisories::forAdmin(freshly($this->admin), null))->contains(fn (array $a): bool => str_contains($a['msg'], 'running low'));

    expect($low())->toBeTrue();

    trackingOff($this->tenant);

    expect($low())->toBeFalse();
});

test('the running-low widget leaves dashboards with it off, and comes back with it on', function () {
    app(SaveDashboardLayout::class)->handle($this->admin, 'desktop', [
        ['i' => 'running_low', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 4],
        ['i' => 'stats', 'x' => 4, 'y' => 0, 'w' => 8, 'h' => 3],
    ]);
    $placed = fn (): array => collect(DashboardWidgets::layoutsFor(freshly($this->admin))['desktop'])->pluck('i')->all();

    expect($placed())->toContain('running_low');

    trackingOff($this->tenant);

    expect($placed())->toBe(['stats'])
        ->and(collect(DashboardWidgets::palette(freshly($this->admin)))->pluck('key'))->not->toContain('running_low');

    $this->tenant->forceFill(['inventory_tracking' => true])->save();

    expect($placed())->toContain('running_low');
});

test('without Running low beside it, Recent activity fills the row', function () {
    $width = fn (): int => collect(DashboardWidgets::layoutsFor(freshly($this->admin))['desktop'])->firstWhere('i', 'recent_visits')['w'];

    expect($width())->toBe(8);

    trackingOff($this->tenant);

    expect($width())->toBe(12);
});

test('the assistant knows, too', function () {
    expect((new LookupInventory)->execute([], $this->admin))->toContain('Muriatic Acid');

    trackingOff($this->tenant);

    expect((new LookupInventory)->availableTo($this->admin))->toBeFalse(); // not offered at all
});

test('the switch reaches the app', function () {
    trackingOff($this->tenant);

    $this->actingAs(freshly($this->admin))->get('/company')->assertInertia(fn (Assert $page) => $page->where('tenant.inventory_tracking', false));
});
