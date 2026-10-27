<?php

declare(strict_types=1);

use App\Actions\SendVisitFollowups;
use App\Mail\VisitRecapMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmailPreferences;
use App\Support\ReportCopies;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
 * A pool's second address (a landlord, a property manager) gets its own copy of
 * every visit report: set from the field app, remembered on the pool, and only
 * that person can stop it — never the customer's own reports.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Glynn Pool Care']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Rental pool']);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $this->tenant->today()]);
    $this->stop = RouteStop::factory()->for($route)->for($this->pool)->create(['stop_order' => 1, 'status' => 'pending']);
});

function finishCopyStop(array $payload): void
{
    test()->actingAs(test()->agent)->postJson('/api/field/visits/'.test()->stop->id.'/complete', $payload)->assertOk();
}

function copiedVisit(): ServiceVisit
{
    return ServiceVisit::factory()->for(test()->tenant)->for(test()->pool)->create([
        'agent_id' => test()->agent->id, 'status' => 'completed', 'completed_at' => now(), 'visited_at' => now(),
    ]);
}

test('the field app remembers the copy-to address on the pool, and the day bundle carries it', function () {
    finishCopyStop(['report_cc_email' => 'manager@example.com']);

    expect($this->pool->fresh()?->report_cc_email)->toBe('manager@example.com');
    $this->actingAs($this->agent)->getJson('/api/field/today')->assertJsonPath('stops.0.pool.report_cc', 'manager@example.com');
});

test('a blank address clears it, and an older app that sends nothing leaves it alone', function () {
    $this->pool->update(['report_cc_email' => 'manager@example.com']);

    finishCopyStop(['notes' => 'All good']); // no key at all
    expect($this->pool->fresh()?->report_cc_email)->toBe('manager@example.com');

    finishCopyStop(['report_cc_email' => '']);
    expect($this->pool->fresh()?->report_cc_email)->toBeNull();
});

test('a mistyped address is refused', function () {
    $this->actingAs($this->agent)
        ->postJson("/api/field/visits/{$this->stop->id}/complete", ['report_cc_email' => 'manager@'])
        ->assertJsonValidationErrors('report_cc_email');
});

test('the copy goes out with the customer’s report, even when they turned their own reports off', function () {
    Mail::fake();
    finishCopyStop(['report_cc_email' => 'manager@example.com']);

    Mail::assertQueued(VisitRecapMail::class, fn (VisitRecapMail $m) => $m->hasTo('jane@example.com') && $m->copyTo === null);
    Mail::assertQueued(VisitRecapMail::class, fn (VisitRecapMail $m) => $m->hasTo('manager@example.com') && $m->copyTo === 'manager@example.com');

    EmailPreferences::set($this->customer, ['visit_reports' => false]);
    Mail::fake();
    app(SendVisitFollowups::class)->handle(copiedVisit()->load('pool'));

    Mail::assertQueuedCount(1);
    Mail::assertQueued(VisitRecapMail::class, fn (VisitRecapMail $m) => $m->hasTo('manager@example.com'));
});

test('no copy when it would go to the customer’s own address', function () {
    Mail::fake();
    finishCopyStop(['report_cc_email' => 'JANE@example.com']);

    Mail::assertQueuedCount(1);
});

test('the copy leaves out the customer’s balance, pay link and portal, and stops only itself', function () {
    $this->pool->update(['report_cc_email' => 'manager@example.com']);
    $copy = new VisitRecapMail(copiedVisit(), 80.0, 'https://example.test/pay', copyTo: 'manager@example.com');

    $html = $copy->render();
    expect($html)->toContain('Jane’s pool was serviced')
        ->toContain('Jane asked Glynn Pool Care to send you a copy')
        ->toContain(e(ReportCopies::pageUrl($this->pool, 'manager@example.com')))
        ->not->toContain('example.test/pay')
        ->not->toContain('Your balance')
        ->not->toContain('email-preferences');
    expect($copy->envelope()->subject)->toBe('Jane’s pool was serviced — Glynn Pool Care')
        ->and($copy->headers()->text['List-Unsubscribe'])->toContain('report-copies');
});

test('the stop page asks first, then stops only the address it was sent to', function () {
    $this->pool->update(['report_cc_email' => 'manager@example.com']);

    $this->get('/report-copies/'.$this->pool->id.'?email=manager@example.com')->assertForbidden(); // unsigned
    $this->get(ReportCopies::pageUrl($this->pool, 'manager@example.com'))->assertOk();
    expect($this->pool->fresh()?->report_cc_email)->toBe('manager@example.com'); // opening it changed nothing

    // An old link for an earlier address can't remove the current one.
    $this->post(ReportCopies::stopUrl($this->pool, 'old@example.com'))->assertRedirect();
    expect($this->pool->fresh()?->report_cc_email)->toBe('manager@example.com');

    $this->post(ReportCopies::stopUrl($this->pool, 'manager@example.com'))->assertRedirect();
    expect($this->pool->fresh()?->report_cc_email)->toBeNull();
});

test('the inbox’s one-click unsubscribe stops the copies without a CSRF token', function () {
    $this->pool->update(['report_cc_email' => 'manager@example.com']);
    $url = URL::signedRoute('report-copies.one-click', ['pool' => $this->pool->id, 'email' => 'manager@example.com']);

    $this->post($url)->assertOk();
    expect($this->pool->fresh()?->report_cc_email)->toBeNull();
});

test('the office sees who gets copies on the pool, and can change or clear it', function () {
    $admin = User::factory()->for($this->tenant)->create();
    $this->pool->update(['report_cc_email' => 'manager@example.com']);

    $this->actingAs($admin)->get('/pools?selected='.$this->pool->id)
        ->assertInertia(fn ($page) => $page->where('selected.report_cc', 'manager@example.com')->where('selected.fields.report_cc_email', 'manager@example.com'));

    $fields = ['customer_id' => $this->customer->id, 'name' => 'Rental pool', 'type' => 'inground', 'sanitizer_type' => 'chlorine'];
    $this->actingAs($admin)->patch('/pools/'.$this->pool->id, $fields + ['report_cc_email' => 'not-an-email'])->assertSessionHasErrors('report_cc_email');
    $this->actingAs($admin)->patch('/pools/'.$this->pool->id, $fields + ['report_cc_email' => 'landlord@example.com'])->assertSessionHasNoErrors();
    expect($this->pool->fresh()?->report_cc_email)->toBe('landlord@example.com');

    $this->actingAs($admin)->patch('/pools/'.$this->pool->id, $fields + ['report_cc_email' => ''])->assertSessionHasNoErrors();
    expect($this->pool->fresh()?->report_cc_email)->toBeNull();
});
