<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->super = User::factory()->create();
    $this->super->forceFill(['role' => 'super_admin', 'tenant_id' => null])->save();
});

test('a super-admin sees the tenants list on the People screen', function () {
    Tenant::factory()->create(['name' => 'Acme Pools']);

    $this->actingAs($this->super)
        ->get('/people')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('people/Platform')->where('filters.type', 'tenants')->has('people.data'));
});

test('a super-admin creates a tenant with a pre-verified admin', function () {
    $this->actingAs($this->super)
        ->post('/tenants', ['company' => 'New Co', 'first_name' => 'Pat', 'email' => 'pat@newco.test', 'password' => 'password123'])
        ->assertRedirect();

    expect(Tenant::query()->where('name', 'New Co')->exists())->toBeTrue();
    $admin = User::query()->where('email', 'pat@newco.test')->first();
    expect($admin?->role)->toBe('tenant_admin');
    expect($admin?->getAttribute('email_verified_at'))->not->toBeNull();
});

test('a super-admin can suspend a tenant', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);

    $this->actingAs($this->super)
        ->patch("/tenants/{$tenant->id}", ['name' => $tenant->name, 'slug' => $tenant->slug, 'status' => 'suspended'])
        ->assertRedirect();

    expect($tenant->fresh()?->getAttribute('status'))->toBe('suspended');
});

test('a super-admin can change a tenant slug (normalized)', function () {
    $tenant = Tenant::factory()->create(['slug' => 'old-slug']);

    $this->actingAs($this->super)
        ->patch("/tenants/{$tenant->id}", ['name' => $tenant->name, 'slug' => 'New Brand Name', 'status' => 'active'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tenant->fresh()?->slug)->toBe('new-brand-name');
});

test('slug edits reject reserved words and collisions and bad characters', function () {
    $a = Tenant::factory()->create(['slug' => 'alpha']);
    Tenant::factory()->create(['slug' => 'beta']);

    $patch = fn (string $slug) => $this->actingAs($this->super)
        ->patch("/tenants/{$a->id}", ['name' => $a->name, 'slug' => $slug, 'status' => 'active']);

    $patch('admin')->assertSessionHasErrors('slug');  // reserved
    $patch('beta')->assertSessionHasErrors('slug');   // taken by another tenant
    $patch('!!!')->assertSessionHasErrors('slug');    // normalizes to empty → required fails
    $patch('alpha')->assertSessionHasNoErrors();      // unchanged = fine (ignores self)

    expect($a->fresh()?->slug)->toBe('alpha');
});

test('a suspended company locks its staff out', function () {
    $tenant = Tenant::factory()->create(['status' => 'suspended']);
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($admin)->get('/dashboard')->assertForbidden();
});

test('a super-admin can impersonate a tenant and return', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($this->super)
        ->post("/tenants/{$tenant->id}/impersonate")
        ->assertRedirect('/dashboard')
        ->assertSessionHas('impersonator_id', $this->super->id);

    $this->assertAuthenticatedAs($admin);
    expect(AuditLog::query()->where('action', 'impersonate.start')->exists())->toBeTrue();

    $this->post('/impersonate/stop')->assertRedirect("/people?type=tenants&selected={$tenant->id}&selected_type=tenant");
    $this->assertAuthenticatedAs($this->super);
});

test('a super-admin can impersonate an agent and return to their row', function () {
    $agent = User::factory()->for(Tenant::factory())->create();
    $agent->forceFill(['role' => 'agent'])->save();

    $this->actingAs($this->super)
        ->post("/users/{$agent->id}/impersonate")
        ->assertRedirect('/dashboard')
        ->assertSessionHas('impersonator_id', $this->super->id);

    $this->assertAuthenticatedAs($agent);
    expect(AuditLog::query()->where('action', 'impersonate.start')->where('model_id', $agent->id)->exists())->toBeTrue();

    $this->post('/impersonate/stop')->assertRedirect("/people?type=agents&selected={$agent->id}&selected_type=agent");
    $this->assertAuthenticatedAs($this->super);
});

test('a super-admin can impersonate a customer through their portal login', function () {
    $tenant = Tenant::factory()->create();
    $login = User::factory()->for($tenant)->create();
    $login->forceFill(['role' => 'customer'])->save();
    $customer = Customer::factory()->for($tenant)->create();
    $customer->forceFill(['user_id' => $login->id])->save();

    $this->actingAs($this->super)
        ->get("/people?type=customers&selected={$customer->id}&selected_type=customer")
        ->assertInertia(fn (Assert $page) => $page->where('selected.login_id', $login->id)->where('people.data.0.portal', true));

    $this->post("/users/{$login->id}/impersonate")->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($login);

    $this->post('/impersonate/stop')->assertRedirect("/people?type=customers&selected={$customer->id}&selected_type=customer");
    $this->assertAuthenticatedAs($this->super);
});

test('a customer without a portal login has nobody to sign in as', function () {
    $customer = Customer::factory()->for(Tenant::factory())->create();

    $this->actingAs($this->super)
        ->get("/people?type=customers&selected={$customer->id}&selected_type=customer")
        ->assertInertia(fn (Assert $page) => $page->where('selected.login_id', null)->where('people.data.0.portal', false));
});

test('another super-admin cannot be impersonated', function () {
    $other = User::factory()->create();
    $other->forceFill(['role' => 'super_admin', 'tenant_id' => null])->save();

    $this->actingAs($this->super)->post("/users/{$other->id}/impersonate")->assertForbidden();
    $this->assertAuthenticatedAs($this->super);
    expect(AuditLog::query()->where('action', 'impersonate.start')->exists())->toBeFalse();
});

test('only a super-admin can impersonate a person', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $agent = User::factory()->for($tenant)->create();
    $agent->forceFill(['role' => 'agent'])->save();

    $this->actingAs($admin)->post("/users/{$agent->id}/impersonate")->assertForbidden();
    $this->assertAuthenticatedAs($admin);
});

test('an audited action while impersonating names the super-admin behind it', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $customer = Customer::factory()->for($tenant)->create();

    $this->actingAs($this->super)->post("/tenants/{$tenant->id}/impersonate");
    app()->forgetInstance('tenant_id');
    app()->forgetInstance('tenant');
    $this->get("/customers/{$customer->id}/export")->assertOk();

    $row = AuditLog::query()->withoutGlobalScopes()->where('action', 'customer.exported')->firstOrFail();
    expect($row->user_id)->toBe($admin->id)
        ->and($row->getAttribute('impersonator_id'))->toBe($this->super->id);
});

test('an action outside impersonation has no impersonator', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();
    $customer = Customer::factory()->for($tenant)->create();

    $this->actingAs($admin)->get("/customers/{$customer->id}/export")->assertOk();

    expect(AuditLog::query()->withoutGlobalScopes()->where('action', 'customer.exported')->value('impersonator_id'))->toBeNull();
});

test('stopping after the super-admin account is gone signs out instead of staying in as them', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->for($tenant)->create();

    $this->actingAs($this->super)->post("/tenants/{$tenant->id}/impersonate");
    $this->super->delete();

    $this->post('/impersonate/stop')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('logging out while impersonating signs everyone out and leaves their "remember me" alone', function () {
    $agent = User::factory()->agent()->for(Tenant::factory())->create();
    $agent->forceFill(['remember_token' => 'agent-remember'])->save();

    $this->actingAs($this->super)->post("/users/{$agent->id}/impersonate");
    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    expect($agent->fresh()?->getRememberToken())->toBe('agent-remember')
        ->and(AuditLog::query()->withoutGlobalScopes()->where('action', 'impersonate.stop')->value('user_id'))->toBe($this->super->id);
});

test('staff cannot create tenants or reach the AI console', function () {
    $admin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($admin)->post('/tenants', ['company' => 'x', 'first_name' => 'y', 'email' => 'z@z.test', 'password' => 'password123'])->assertForbidden();
    $this->actingAs($admin)->get('/platform/ai')->assertForbidden();
});
