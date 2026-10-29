<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The office deletes customer requests and website leads from the inbox — one
 * from its pane, or several ticked in the list. Only its own, only admins, each
 * one audit-logged, and what they led to (a plan, a customer) stays.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

function inboxRequest(array $attrs = []): ServiceRequest
{
    return ServiceRequest::create(array_merge(['customer_id' => test()->customer->id, 'pool_id' => test()->pool->id, 'type' => 'other', 'message' => 'Hi'], $attrs));
}

function inboxLead(string $name = 'Pat Prospect'): Lead
{
    return Lead::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test', 'source' => 'contact']);
}

test('an admin deletes one request, and it is audit-logged without the person’s details', function () {
    $request = inboxRequest(['message' => 'Call me on 912-555-0100']);

    $this->actingAs($this->admin)->delete('/requests', ['ids' => [$request->id]])->assertRedirect()->assertSessionHas('success', 'Request deleted.');

    expect(ServiceRequest::query()->count())->toBe(0);
    $audit = AuditLog::query()->where('action', 'request.deleted')->sole();
    expect($audit->model_id)->toBe($request->id)
        ->and(json_encode($audit->changes))->not->toContain('912-555-0100');
});

test('several leads go at once; another company’s are left alone', function () {
    $mine = [inboxLead('Pat One')->id, inboxLead('Pat Two')->id];
    $keep = inboxLead('Pat Three');

    $other = Tenant::factory()->create();
    app()->instance('tenant_id', $other->id);
    $theirs = inboxLead('Theirs');
    app()->instance('tenant_id', $this->tenant->id);

    $this->actingAs($this->admin)->delete('/leads', ['ids' => [...$mine, $theirs->id]])->assertSessionHas('success', '2 leads deleted.');

    expect(Lead::query()->pluck('id')->all())->toBe([$keep->id])
        ->and(Lead::query()->withoutGlobalScopes()->whereKey($theirs->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'lead.deleted')->count())->toBe(2);
});

test('what a request or lead led to stays: the plan, the customer', function () {
    $type = ServiceType::factory()->for($this->tenant)->create();
    $plan = ServiceSubscription::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'service_type_id' => $type->id]);
    $request = inboxRequest(['type' => 'service', 'service_type_id' => $type->id]);
    $request->forceFill(['status' => 'approved', 'service_subscription_id' => $plan->id])->save();
    $lead = inboxLead();
    $lead->forceFill(['status' => 'converted', 'customer_id' => $this->customer->id])->save();

    $this->actingAs($this->admin)->delete('/requests', ['ids' => [$request->id]]);
    $this->actingAs($this->admin)->delete('/leads', ['ids' => [$lead->id]]);

    expect(ServiceSubscription::query()->whereKey($plan->id)->exists())->toBeTrue()
        ->and(Customer::query()->whereKey($this->customer->id)->exists())->toBeTrue();
});

test('a link to a deleted request just opens the inbox', function () {
    $request = inboxRequest();
    $this->actingAs($this->admin)->delete('/requests', ['ids' => [$request->id]]);

    $this->actingAs($this->admin)->get('/inbox?selected='.$request->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('only admins can delete, and they must say what', function () {
    $request = inboxRequest();
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->delete('/requests', ['ids' => [$request->id]])->assertForbidden();
    $this->actingAs($agent)->delete('/leads', ['ids' => [inboxLead()->id]])->assertForbidden();
    $this->actingAs($this->admin)->delete('/requests', ['ids' => []])->assertSessionHasErrors('ids');

    expect(ServiceRequest::query()->count())->toBe(1);
});
