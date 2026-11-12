<?php

declare(strict_types=1);

use App\Models\AiUsage;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\AiQuota;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('the AI quota charges answered messages and counts down', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '2');
    $quota = app(AiQuota::class);

    expect($quota->remaining($this->tenant->id))->toBe(2);
    $quota->charge($this->tenant->id, 1500, 0.0012);
    $quota->charge($this->tenant->id, 500, 0.0003);

    $usage = AiUsage::query()->where('tenant_id', $this->tenant->id)->sole();
    expect($quota->used($this->tenant->id))->toBe(2)
        ->and($quota->remaining($this->tenant->id))->toBe(0)
        ->and((int) $usage->getAttribute('tokens'))->toBe(2000)
        ->and((float) $usage->getAttribute('cost'))->toEqualWithDelta(0.0015, 0.000001);
});

test('the assistant blocks once the monthly allowance is used up', function () {
    TenantSetting::setFor($this->tenant->id, 'ai_monthly_quota', '0');

    $this->actingAs($this->admin)
        ->postJson('/assistant/send', ['message' => 'hello'])
        ->assertStatus(429);
});
