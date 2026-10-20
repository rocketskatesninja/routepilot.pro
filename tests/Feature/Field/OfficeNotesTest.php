<?php

declare(strict_types=1);

use App\Actions\AnonymizeCustomer;
use App\Mail\VisitRecapMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\Tools\LookupServiceHistory;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A visit's office note is for the company only. These pin down that it's saved
 * and shown to staff, and that nothing a homeowner can reach ever carries it.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->portalUser = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'last_name' => 'Anderson']);
    $this->customer->forceFill(['user_id' => $this->portalUser->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

function officeNoteVisit(): ServiceVisit
{
    return ServiceVisit::factory()->for(test()->tenant)->for(test()->pool)->create([
        'agent_id' => test()->agent->id,
        'status' => 'completed',
        'completed_at' => now(),
        'visited_at' => now(),
        'notes' => 'Water looks great.',
        'office_notes' => 'Pump seal is leaking',
    ]);
}

test('the field app saves the office note apart from the homeowner note', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $stop = RouteStop::factory()->for($route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);

    $this->actingAs($this->agent)
        ->postJson("/api/field/visits/{$stop->id}/complete", ['notes' => 'Water looks great.', 'office_notes' => 'Pump seal is leaking'])
        ->assertOk();

    $visit = ServiceVisit::query()->where('route_stop_id', $stop->id)->sole();
    expect($visit->notes)->toBe('Water looks great.')
        ->and($visit->office_notes)->toBe('Pump seal is leaking');
});

test('staff see the office note on the report and can edit it', function () {
    $visit = officeNoteVisit();

    $this->actingAs($this->admin)
        ->get("/reports?selected={$visit->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.office_notes', 'Pump seal is leaking'));

    $this->actingAs($this->admin)
        ->patch("/reports/{$visit->id}", ['notes' => 'Water looks great.', 'office_notes' => 'Seal replaced, ordered a spare'])
        ->assertRedirect();

    expect($visit->fresh()?->office_notes)->toBe('Seal replaced, ordered a spare');
});

test('the homeowner never sees the office note in the portal', function () {
    officeNoteVisit();

    // The history page does show this visit (its homeowner note), so the check below means something.
    $this->actingAs($this->portalUser)->get('/history')->assertSee('Water looks great.');

    foreach (['/history', '/dashboard', '/my-pools'] as $page) {
        $this->actingAs($this->portalUser)->get($page)->assertOk()->assertDontSee('Pump seal is leaking');
    }
});

test('the visit email never carries the office note', function () {
    $html = (new VisitRecapMail(officeNoteVisit(), 0.0, null))->render();

    expect($html)->toContain('Water looks great.')->not->toContain('Pump seal is leaking');
});

test('the assistant\'s service history never reads out the office note', function () {
    officeNoteVisit();

    $out = app(LookupServiceHistory::class)->execute(['customer_name' => 'Robert Anderson'], $this->tenant->id);

    expect($out)->toContain('Water looks great.')->not->toContain('Pump seal is leaking');
});

test('a visit turned into JSON wholesale leaves the office note out', function () {
    expect(officeNoteVisit()->toArray())->not->toHaveKey('office_notes');
});

test('removing a customer\'s personal details clears office notes too', function () {
    $visit = officeNoteVisit();
    $this->customer->delete(); // archived first, as the screen requires

    app(AnonymizeCustomer::class)->handle($this->customer->fresh() ?? $this->customer);

    expect($visit->fresh()?->office_notes)->toBeNull()
        ->and($visit->fresh()?->notes)->toBeNull();
});
