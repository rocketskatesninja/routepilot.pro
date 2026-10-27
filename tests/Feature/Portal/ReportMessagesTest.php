<?php

declare(strict_types=1);

use App\Mail\ServiceReminderMail;
use App\Mail\ServiceRequestAnsweredMail;
use App\Mail\VisitRecapMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Notifications\ServiceRequestSubmitted;
use App\Support\ReportMessages;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A customer can message the company from a visit report email (no login): it
 * lands in the Requests inbox with the visit attached, and the answer comes
 * back by email. Replies to the company's emails reach the company's own address.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Glynn Pool Care']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Backyard pool']);
    $this->visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
        'status' => 'completed', 'completed_at' => now(), 'visited_at' => now(),
    ]);
});

/** Post a message the way the page does: to the signed send address it was given. */
function sendReportMessage(string $message): TestResponse
{
    app()->forgetInstance('tenant_id'); // a public page: no signed-in company
    $page = test()->get(ReportMessages::linkFor(test()->visit));
    $sendUrl = $page->viewData('page')['props']['sendUrl'];

    return test()->post($sendUrl, ['message' => $message]);
}

test('the report email has a "Message" button, unless the company turned it off or it’s a copy', function () {
    expect((new VisitRecapMail($this->visit, 0.0, null))->render())->toContain('Message Glynn Pool Care')->toContain('report-message/');

    expect((new VisitRecapMail($this->visit, 0.0, null, copyTo: 'manager@example.com'))->render())->not->toContain('report-message/');

    TenantSetting::setFor($this->tenant->id, 'report_messages', '0');
    expect((new VisitRecapMail($this->visit, 0.0, null))->render())->not->toContain('report-message/');
});

test('the page needs the signed link, expires, and is gone when the company turned it off', function () {
    $link = ReportMessages::linkFor($this->visit);
    app()->forgetInstance('tenant_id');

    $this->get('/report-message/'.$this->visit->id)->assertForbidden();
    $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('public/ReportMessage')
        ->where('company.name', 'Glynn Pool Care')
        ->where('firstName', 'Jane')
        ->where('visit.pool', 'Backyard pool'));

    $this->travel(31)->days();
    $this->get($link)->assertForbidden();
    $this->travelBack();

    TenantSetting::setFor($this->tenant->id, 'report_messages', '0');
    $this->get(ReportMessages::linkFor($this->visit))->assertNotFound();
});

test('a message lands in the Requests inbox with the visit attached, and the office is told', function () {
    Notification::fake();

    sendReportMessage('The water still looks cloudy.')->assertRedirect()->assertSessionHasNoErrors();

    $request = ServiceRequest::query()->withoutGlobalScopes()->sole();
    expect($request->tenant_id)->toBe($this->tenant->id)
        ->and($request->type)->toBe('other')
        ->and($request->status)->toBe('pending')
        ->and($request->getAttribute('pool_id'))->toBe($this->pool->id)
        ->and($request->getAttribute('service_visit_id'))->toBe($this->visit->id)
        ->and($request->message)->toBe('The water still looks cloudy.')
        ->and($request->title())->toBe('Message about a visit');
    Notification::assertSentTo($this->admin, ServiceRequestSubmitted::class);

    app()->instance('tenant_id', $this->tenant->id);
    $this->actingAs($this->admin)->get('/inbox?selected='.$request->id)
        ->assertInertia(fn (Assert $page) => $page->where('selected.visit.id', $this->visit->id));
});

test('a customer can have only three unanswered messages waiting', function () {
    foreach (['One', 'Two', 'Three'] as $m) {
        sendReportMessage($m)->assertSessionHasNoErrors();
    }

    sendReportMessage('Four')->assertSessionHasErrors('message');
    expect(ServiceRequest::query()->withoutGlobalScopes()->count())->toBe(3);
});

test('answering the message emails the customer about their visit', function () {
    sendReportMessage('Can you check the filter next time?');
    $request = ServiceRequest::query()->withoutGlobalScopes()->sole();
    Mail::fake();

    app()->instance('tenant_id', $this->tenant->id);
    $this->actingAs($this->admin)->post("/requests/{$request->id}/approve", ['reply' => 'Will do!'])->assertRedirect();

    Mail::assertQueued(ServiceRequestAnsweredMail::class, fn (ServiceRequestAnsweredMail $m) => $m->hasTo('jane@example.com')
        && $m->headline === 'About your message'
        && str_contains($m->summary, 'Glynn Pool Care got back to you about your')
        && $m->reply === 'Will do!');
});

test('replies to the company’s emails go to its own address, once it has set one', function () {
    $recap = fn () => new VisitRecapMail($this->visit->fresh(), 0.0, null);
    expect($recap()->envelope()->replyTo)->toBe([]);

    $this->tenant->update(['reply_to_email' => 'office@glynnpoolcare.com']);

    expect($recap()->envelope()->replyTo[0]->address)->toBe('office@glynnpoolcare.com')
        ->and($recap()->envelope()->replyTo[0]->name)->toBe('Glynn Pool Care');
    $reminder = new ServiceReminderMail('Jane', 'Glynn Pool Care', 'Backyard pool', 'Tuesday', null, $this->tenant->fresh());
    expect($reminder->envelope()->replyTo[0]->address)->toBe('office@glynnpoolcare.com');
});

test('the Company page saves the reply-to address and the message switch (on by default)', function () {
    $fields = ['name' => 'Glynn Pool Care', 'timezone' => 'America/New_York', 'brand_color' => '#0ea5e9', 'tax_rate_percent' => 0];

    $this->actingAs($this->admin)->get('/company')
        ->assertInertia(fn (Assert $page) => $page->where('company.report_messages', true)->where('company.reply_to_email', null));

    $this->actingAs($this->admin)->patch('/company', $fields + ['reply_to_email' => 'nope'])->assertSessionHasErrors('reply_to_email');
    $this->actingAs($this->admin)->patch('/company', $fields + ['reply_to_email' => 'office@glynnpoolcare.com', 'report_messages' => false])
        ->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();
    expect($tenant?->reply_to_email)->toBe('office@glynnpoolcare.com')
        ->and($tenant?->takesReportMessages())->toBeFalse();
});
