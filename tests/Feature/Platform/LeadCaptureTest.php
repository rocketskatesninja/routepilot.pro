<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme-pools']);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('the public form captures a lead and notifies admins', function () {
    $this->postJson('/public/acme-pools/leads', ['name' => 'Pat Homeowner', 'email' => 'pat@x.test', 'source' => 'quote', 'message' => 'Weekly service quote?'])
        ->assertOk();

    expect(Lead::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Pat Homeowner')->exists())->toBeTrue();
    expect($this->admin->notifications()->count())->toBe(1);
});

test('the lead form requires a name', function () {
    $this->postJson('/public/acme-pools/leads', ['email' => 'x@x.test'])->assertStatus(422);
});

test('an admin sees the leads inbox and updates status', function () {
    app()->instance('tenant_id', $this->tenant->id);
    $lead = Lead::create(['name' => 'Jo', 'email' => 'jo@x.test', 'source' => 'contact']);

    $this->actingAs($this->admin)
        ->get('/insights')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reports/Insights')->has('leads', 1));

    $this->actingAs($this->admin)->patch("/leads/{$lead->id}", ['status' => 'contacted'])->assertRedirect();
    expect($lead->fresh()?->status)->toBe('contacted');
});

test('agents cannot see the leads inbox', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/insights')->assertForbidden();
});
