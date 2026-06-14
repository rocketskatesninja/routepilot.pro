<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

function completedVisit(Tenant $tenant, User $agent): ServiceVisit
{
    $pool = Pool::factory()->for($tenant)->for(Customer::factory()->for($tenant)->create())->create();

    return ServiceVisit::factory()->for($tenant)->for($pool)->create([
        'agent_id' => $agent->id, 'status' => 'completed', 'completed_at' => now(),
    ]);
}

test('an admin can edit any report (notes + reading + treatments + tasks)', function () {
    $visit = completedVisit($this->tenant, $this->agent);

    $this->actingAs($this->admin)->patch("/reports/{$visit->id}", [
        'completed_on' => now()->toDateString(),
        'notes' => 'Edited by office',
        'reading' => ['ph' => 7.4, 'free_chlorine' => 2.0],
        'treatments' => [['name' => 'Muriatic Acid', 'amount' => 8, 'unit' => 'oz']],
        'tasks' => [['name' => 'Brushed walls', 'done' => true]],
    ])->assertRedirect();

    $visit->refresh();
    expect($visit->notes)->toBe('Edited by office')
        ->and((float) $visit->chemicalReading()->first()?->ph)->toBe(7.4)
        ->and($visit->treatments()->count())->toBe(1)
        ->and($visit->tasks()->where('is_completed', true)->count())->toBe(1);
});

test('an agent can edit their own report', function () {
    $visit = completedVisit($this->tenant, $this->agent);

    $this->actingAs($this->agent)->patch("/reports/{$visit->id}", [
        'notes' => 'Edited by tech',
        'reading' => ['ph' => 7.6],
    ])->assertRedirect();

    expect($visit->fresh()?->notes)->toBe('Edited by tech');
});

test("an agent cannot edit another agent's report", function () {
    $other = User::factory()->agent()->for($this->tenant)->create();
    $visit = completedVisit($this->tenant, $other);

    $this->actingAs($this->agent)->patch("/reports/{$visit->id}", ['notes' => 'Hack'])->assertForbidden();
    expect($visit->fresh()?->notes)->not->toBe('Hack');
});

test('a foreign-tenant report cannot be edited', function () {
    $other = Tenant::factory()->create();
    $foreign = completedVisit($other, User::factory()->agent()->for($other)->create());

    $this->actingAs($this->admin)->patch("/reports/{$foreign->id}", ['notes' => 'x'])->assertNotFound();
});
