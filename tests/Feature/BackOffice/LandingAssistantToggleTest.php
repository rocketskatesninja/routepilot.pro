<?php

declare(strict_types=1);

use App\Actions\RegisterTenant;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OnboardingStatus;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A company can switch off its public website and the AI assistant. Its web
 * address keeps working, with its name and a customer login.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Glynn Pool Care', 'slug' => 'glynn', 'status' => 'active']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customerUser = User::factory()->customer()->for($this->tenant)->create();
    Customer::factory()->for($this->tenant)->create(['user_id' => $this->customerUser->id]);
});

function turnOff(Tenant $tenant, string $feature): void
{
    $tenant->forceFill([$feature => false])->save();
}

function asFresh(User $user): User
{
    return User::query()->findOrFail($user->id);
}

test('every company starts with both on', function () {
    expect($this->tenant->landing_page)->toBeTrue()->and($this->tenant->ai_assistant)->toBeTrue()
        ->and($this->tenant->fresh()?->landing_page)->toBeTrue()->and($this->tenant->fresh()?->ai_assistant)->toBeTrue();

    $user = app(RegisterTenant::class)(['company' => 'New Pools', 'first_name' => 'Ana', 'email' => 'ana@homeowner.co', 'password' => 'a-long-password']);

    expect($user->tenant?->landing_page)->toBeTrue()->and($user->tenant?->ai_assistant)->toBeTrue();
});

test('an admin can switch each one, on its own, and it’s audited', function (string $feature, string $said) {
    $this->actingAs($this->admin)->patch('/company/features', [$feature => false])->assertRedirect()->assertSessionHas('success', $said);

    $tenant = $this->tenant->fresh();
    expect($tenant?->getAttribute($feature))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'company.'.$feature)->count())->toBe(1);
    foreach (array_diff(array_keys(Tenant::FEATURES), [$feature]) as $other) {
        expect($tenant?->getAttribute($other))->toBeTrue();
    }
})->with([
    ['landing_page', 'Your website is off.'],
    ['ai_assistant', 'The AI assistant is off.'],
]);

test('only the admin can change them', function () {
    $this->actingAs($this->agent)->patch('/company/features', ['landing_page' => false])->assertForbidden();
    $this->actingAs($this->customerUser)->patch('/company/features', ['ai_assistant' => false])->assertForbidden();

    expect($this->tenant->fresh()?->landing_page)->toBeTrue()->and($this->tenant->fresh()?->ai_assistant)->toBeTrue();
});

test('saving nothing is refused', function () {
    $this->actingAs($this->admin)->patch('/company/features', [])->assertSessionHasErrors();
});

test('with its website on, the address shows the website', function () {
    $this->get('/t/glynn')->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/Landing'));
});

test('with its website off, the address shows its name and a login, not the website', function () {
    turnOff($this->tenant, 'landing_page');

    $this->get('/t/glynn')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/CompanyHome')
        ->where('company.name', 'Glynn Pool Care')
        ->missing('sections')
        ->missing('live'));
});

test('a company’s own domain gets the same page', function () {
    $this->tenant->forceFill(['primary_domain' => 'glynnpoolcare.co'])->save();
    turnOff($this->tenant, 'landing_page');
    app()->forgetInstance('tenant_id');

    $this->get('https://glynnpoolcare.co/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/CompanyHome'));
});

test('a closed company is still not found', function () {
    turnOff($this->tenant, 'landing_page');
    $this->tenant->forceFill(['status' => 'suspended'])->save();

    $this->get('/t/glynn')->assertNotFound();
});

test('with its website off, the editor is gone', function () {
    turnOff($this->tenant, 'landing_page');
    $admin = asFresh($this->admin);

    $this->actingAs($admin)->get('/company/landing')->assertNotFound();
    $this->actingAs($admin)->post('/company/landing', [])->assertNotFound();
    $this->actingAs($admin)->post('/company/landing/image', [])->assertNotFound();
});

test('the editor is there while it’s on', function () {
    $this->actingAs($this->admin)->get('/company/landing')->assertOk();
});

test('with its website off, its contact form and chat stop', function () {
    turnOff($this->tenant, 'landing_page');

    $this->post('/public/glynn/leads', ['name' => 'Someone', 'email' => 'someone@homeowner.co'])->assertNotFound();
    $this->post('/public/glynn/chat', ['message' => 'hello'])->assertNotFound();
});

test('its contact form works while it’s on', function () {
    $this->post('/public/glynn/leads', ['name' => 'Someone', 'email' => 'someone@homeowner.co', 'message' => 'A quote please'])
        ->assertOk();
});

test('a company with no website has no landing page step to finish', function () {
    ServiceType::factory()->for($this->tenant)->create();
    expect(collect(OnboardingStatus::for($this->tenant->fresh())['steps'])->pluck('key'))->toContain('landing')
        ->and(OnboardingStatus::for($this->tenant->fresh())['total'])->toBe(4);

    turnOff($this->tenant, 'landing_page');

    $status = OnboardingStatus::for($this->tenant->fresh());
    expect(collect($status['steps'])->pluck('key'))->not->toContain('landing')
        ->and($status['total'])->toBe(3)
        ->and($status['done'])->toBe(2); // its services and its customer
});

test('the assistant is there for everyone while it’s on', function () {
    foreach ([$this->admin, $this->agent, $this->customerUser] as $user) {
        $this->actingAs($user)->get('/assistant')->assertOk();
    }
});

test('with the assistant off, it’s gone for everyone in the company', function () {
    turnOff($this->tenant, 'ai_assistant');

    foreach ([$this->admin, $this->agent, $this->customerUser] as $user) {
        $this->actingAs(asFresh($user))->get('/assistant')->assertNotFound();
        $this->actingAs(asFresh($user))->postJson('/assistant/send', ['message' => 'hi'])->assertNotFound();
    }
});

test('it only affects that company', function () {
    $other = Tenant::factory()->create();
    $theirs = User::factory()->for($other)->create();
    turnOff($this->tenant, 'ai_assistant');

    $this->actingAs($theirs)->get('/assistant')->assertOk();
});

test('a super admin, with no company, keeps the assistant', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/assistant')->assertOk();
});

test('the website’s chat is separate from the assistant', function () {
    turnOff($this->tenant, 'ai_assistant');

    $this->post('/public/glynn/chat', ['message' => 'hello'], ['Accept' => 'application/json'])->assertOk();
});

test('the switches reach the app', function () {
    turnOff($this->tenant, 'landing_page');
    turnOff($this->tenant, 'ai_assistant');

    $this->actingAs(asFresh($this->admin))->get('/company')->assertInertia(fn (Assert $page) => $page
        ->where('tenant.landing_page', false)->where('tenant.ai_assistant', false));
});
