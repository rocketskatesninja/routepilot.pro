<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A report's date is the company's own day: a visit finished at 9:41 pm in New
 * York is that evening's, even though it's already tomorrow in UTC. Editing a
 * report keeps it that way. And names only link to People for techs, the only
 * people it opens.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->tenant->forceFill(['timezone' => 'America/New_York'])->save();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();
    // 9:41 pm on Oct 23 in New York; 01:41 on Oct 24 in UTC.
    $this->visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => Carbon::parse('2026-10-24 01:41:57', 'UTC'),
    ]);
});

test('an evening visit shows on its own day, in the list and the report', function () {
    $this->actingAs($this->admin)
        ->get("/reports?selected={$this->visit->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.completed_on', '2026-10-23')
            ->where('selected.completed_on', '2026-10-23')
        );
});

test('saving a report without changing its date leaves the time exactly as it was', function () {
    $this->actingAs($this->admin)->patch("/reports/{$this->visit->id}", ['completed_on' => '2026-10-23', 'notes' => 'Edited'])->assertRedirect();

    expect($this->visit->fresh()?->completed_at?->utc()->toDateTimeString())->toBe('2026-10-24 01:41:57');
});

test('moving a report to another day keeps its time of day in the company\'s zone', function () {
    $this->actingAs($this->admin)->patch("/reports/{$this->visit->id}", ['completed_on' => '2026-10-21'])->assertRedirect();

    // 9:41 pm on Oct 21 in New York.
    expect($this->visit->fresh()?->completed_at?->utc()->toDateTimeString())->toBe('2026-10-22 01:41:57');
    $this->actingAs($this->admin)->get("/reports?selected={$this->visit->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.completed_on', '2026-10-21'));
});

test('a report links its tech to People, but names an office admin who did the visit without a link', function () {
    $this->actingAs($this->admin)->get("/reports?selected={$this->visit->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.agent_id', $this->agent->id));

    $this->visit->update(['agent_id' => $this->admin->id]);
    $this->actingAs($this->admin)->get("/reports?selected={$this->visit->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected.agent', $this->admin->displayName())->where('selected.agent_id', null));
});

test('a plan links its tech to People, but not an office admin it is assigned to', function () {
    $plan = fn (User $who) => ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create())->create(['assigned_agent_id' => $who->id, 'status' => 'active']);
    $plan($this->agent);
    $plan($this->admin);

    $this->actingAs($this->admin)->get("/pools?selected={$this->pool->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.subscriptions.0.agent_is_tech', true)
            ->where('selected.subscriptions.1.agent_is_tech', false)
            ->where('selected.subscriptions.1.agent_id', $this->admin->id) // still there for the plan's edit form
        );
});
