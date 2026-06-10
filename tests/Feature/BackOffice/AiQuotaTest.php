<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\AiQuota;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('the AI quota records messages and counts down', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '2');
    $quota = app(AiQuota::class);

    expect($quota->remaining($this->tenant->id))->toBe(2);
    $quota->record($this->tenant->id);
    expect($quota->used($this->tenant->id))->toBe(1);
    expect($quota->remaining($this->tenant->id))->toBe(1);
});

test('the assistant blocks once the monthly allowance is used up', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '0');

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'hello'])
        ->assertStatus(429);
});
