<?php

declare(strict_types=1);

use App\Mail\CampaignMail;
use App\Mail\ServiceReminderMail;
use App\Mail\VisitRecapMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmailPreferences;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Blue Lagoon Pools']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->portalUser = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'email' => 'robert@example.test']);
    $this->customer->forceFill(['user_id' => $this->portalUser->id])->save();
});

test('opening a "stop visit reports" link only asks — nothing changes until they tap', function () {
    $this->get(EmailPreferences::pageUrl($this->customer->id, 'visit_reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/EmailPreferences')
            ->where('stop', 'visit_reports')
            ->where('firstName', 'Robert')
            ->where('company.name', 'Blue Lagoon Pools')
            ->where('preferences.visit_reports', true));

    expect($this->customer->fresh()->email_visit_reports)->toBeTrue();
});

test('tapping stop turns off just that email, and they can turn it back on', function () {
    $update = URL::signedRoute('email-preferences.update', ['customer' => $this->customer->id]);

    $this->post($update, ['visit_reports' => false, 'stop' => 'visit_reports'])->assertRedirectContains('stop=visit_reports');
    $fresh = $this->customer->fresh();
    expect($fresh->email_visit_reports)->toBeFalse()->and($fresh->email_reminders)->toBeTrue()->and($fresh->email_news)->toBeTrue();

    $this->post($update, ['visit_reports' => true]);
    expect($this->customer->fresh()->email_visit_reports)->toBeTrue();
});

test('a tampered or unsigned link is refused', function () {
    $this->get(EmailPreferences::pageUrl($this->customer->id).'x')->assertForbidden();
    $this->get('/email-preferences/'.$this->customer->id)->assertForbidden();
    $this->post('/email-preferences/'.$this->customer->id, ['news' => false])->assertForbidden();
    expect($this->customer->fresh()->email_news)->toBeTrue();
});

test('the inbox\'s own one-click unsubscribe turns that email off', function () {
    $this->post(EmailPreferences::oneClickUrl($this->customer->id, 'news'), ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($this->customer->fresh()->email_news)->toBeFalse()
        ->and($this->customer->fresh()->email_visit_reports)->toBeTrue();
    $this->get(EmailPreferences::oneClickUrl($this->customer->id, 'news'))->assertMethodNotAllowed(); // a link-checker GET does nothing
});

test('optional emails carry the inbox unsubscribe header and a stop link; required ones don\'t', function () {
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create([
        'status' => 'completed', 'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
    ]);

    $recap = new VisitRecapMail($visit, 0.0, null);
    expect($recap->headers()->text['List-Unsubscribe'])->toContain('/one-click/visit_reports')
        ->and($recap->headers()->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click')
        ->and($recap->render())->toContain('Stop visit reports');

    $reminder = new ServiceReminderMail('Robert', 'Blue Lagoon Pools', 'Backyard Pool', 'Monday', null, null, $this->tenant, $this->customer->id);
    expect($reminder->headers()->text['List-Unsubscribe'])->toContain('/one-click/reminders')
        ->and($reminder->render())->toContain('Stop reminders');

    $news = new CampaignMail('Spring', 'Hi', 'Robert', EmailPreferences::pageUrl($this->customer->id, 'news'), null, $this->tenant, EmailPreferences::oneClickUrl($this->customer->id, 'news'));
    expect($news->headers()->text['List-Unsubscribe'])->toContain('/one-click/news');

    $staffBroadcast = new CampaignMail('Update', 'Hi', 'Sam', null);
    expect($staffBroadcast->headers()->text)->toBe([]);
});

test('a customer manages their emails in their portal settings', function () {
    $this->actingAs($this->portalUser)->get('/settings/emails')
        ->assertInertia(fn (Assert $page) => $page->component('settings/Emails')->where('preferences.reminders', true));

    $this->actingAs($this->portalUser)->patch('/settings/emails', ['reminders' => false])->assertRedirect();
    expect($this->customer->fresh()->email_reminders)->toBeFalse();

    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->actingAs($agent)->get('/settings/emails')->assertForbidden();
});

test('the office can change a customer\'s emails; agents and other companies can\'t', function () {
    $admin = User::factory()->for($this->tenant)->create();
    $this->actingAs($admin)->patch("/customers/{$this->customer->id}/emails", ['news' => false])->assertRedirect();
    expect($this->customer->fresh()->email_news)->toBeFalse();

    $this->actingAs($admin)->get("/people?selected={$this->customer->id}&selected_type=customer")
        ->assertInertia(fn (Assert $page) => $page->where('selected.emails.news', false));

    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->actingAs($agent)->patch("/customers/{$this->customer->id}/emails", ['news' => true])->assertForbidden();

    $other = Tenant::factory()->create();
    $otherAdmin = User::factory()->for($other)->create();
    app()->forgetInstance('tenant_id');
    app()->forgetInstance('tenant');
    $this->actingAs($otherAdmin)->patch("/customers/{$this->customer->id}/emails", ['news' => true])->assertNotFound();
    expect($this->customer->fresh()->email_news)->toBeFalse();
});
