<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('an admin can create an agent with privilege fields set safely', function () {
    $this->actingAs($this->admin)
        ->post('/agents', [
            'first_name' => 'New', 'last_name' => 'Agent', 'email' => 'agent@new.test',
            'password' => 'password123', 'agent_plus' => true,
        ])
        ->assertRedirect();

    $agent = User::query()->where('email', 'agent@new.test')->first();
    expect($agent?->role)->toBe('agent');
    expect($agent?->tenant_id)->toBe($this->tenant->id);
    expect((bool) $agent?->getAttribute('is_active'))->toBeTrue();
    expect((bool) $agent?->getAttribute('agent_plus'))->toBeTrue();
});

test('an admin can edit and deactivate an agent', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->patch("/agents/{$agent->id}", ['first_name' => 'Renamed', 'email' => $agent->getAttribute('email'), 'is_active' => false, 'agent_plus' => true])
        ->assertRedirect();

    expect((bool) $agent->fresh()?->getAttribute('is_active'))->toBeFalse();
    expect((bool) $agent->fresh()?->getAttribute('agent_plus'))->toBeTrue();
});

test('an admin can soft-delete an agent', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)->delete("/agents/{$agent->id}")->assertRedirect();

    $this->assertSoftDeleted('users', ['id' => $agent->id]);
});

test('a foreign-tenant agent cannot be edited', function () {
    $other = Tenant::factory()->create();
    $foreign = User::factory()->agent()->for($other)->create();

    $this->actingAs($this->admin)
        ->patch("/agents/{$foreign->id}", ['first_name' => 'Hack', 'email' => $foreign->getAttribute('email')])
        ->assertNotFound();
});

test('agent email must be unique', function () {
    User::factory()->agent()->for($this->tenant)->create(['email' => 'taken@x.test']);

    $this->actingAs($this->admin)
        ->post('/agents', ['first_name' => 'X', 'email' => 'taken@x.test', 'password' => 'password123'])
        ->assertInvalid('email');
});

test('agents cannot manage agents', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/agents', ['first_name' => 'X'])->assertForbidden();
});

test('an admin can set an agent route colour', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->patch("/agents/{$agent->id}/color", ['map_color' => '#FF8800'])
        ->assertRedirect();

    // Stored canonical (lowercased).
    expect($agent->fresh()?->getAttribute('map_color'))->toBe('#ff8800');
});

test('a non-hex route colour is rejected', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->patch("/agents/{$agent->id}/color", ['map_color' => 'red; background:url(x)'])
        ->assertInvalid('map_color');
});

test('a foreign-tenant agent colour cannot be changed', function () {
    $other = Tenant::factory()->create();
    $foreign = User::factory()->agent()->for($other)->create();

    $this->actingAs($this->admin)
        ->patch("/agents/{$foreign->id}/color", ['map_color' => '#000000'])
        ->assertNotFound();
});

test('agents cannot recolour routes', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)
        ->patch("/agents/{$agent->id}/color", ['map_color' => '#123456'])
        ->assertForbidden();
});
