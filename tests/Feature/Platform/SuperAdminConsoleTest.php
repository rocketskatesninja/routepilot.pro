<?php

declare(strict_types=1);

use App\Models\AuditLog;
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

    $this->post('/impersonate/stop')->assertRedirect('/people');
    $this->assertAuthenticatedAs($this->super);
});

test('staff cannot create tenants or reach the AI console', function () {
    $admin = User::factory()->for(Tenant::factory())->create();

    $this->actingAs($admin)->post('/tenants', ['company' => 'x', 'first_name' => 'y', 'email' => 'z@z.test', 'password' => 'password123'])->assertForbidden();
    $this->actingAs($admin)->get('/platform/ai')->assertForbidden();
});
