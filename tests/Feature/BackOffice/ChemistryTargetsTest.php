<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChemistryService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

test('an admin sets per-pool chemistry targets and the engine uses them', function () {
    $this->actingAs($this->admin)
        ->post("/pools/{$this->pool->id}/targets", [
            'targets' => ['ph' => ['min' => 7.2, 'max' => 7.6], 'free_chlorine' => ['min' => 2, 'max' => 4]],
        ])
        ->assertRedirect();

    $fresh = $this->pool->fresh();
    expect($fresh?->custom_target_ranges['ph']['min'])->toEqual(7.2);

    $ranges = app(ChemistryService::class)->targetsFor($fresh);
    expect($ranges['ph']['min'])->toBe(7.2);
    expect($ranges['ph']['max'])->toBe(7.6);
});

test('agents cannot set chemistry targets', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post("/pools/{$this->pool->id}/targets", ['targets' => []])->assertForbidden();
});
