<?php

declare(strict_types=1);

use App\Jobs\SendCampaignEmail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('each recipient gets a delivery row that lands as sent, tallied on the campaign', function () {
    Customer::factory()->for($this->tenant)->create(['email' => 'a@x.test']);
    Customer::factory()->for($this->tenant)->create(['email' => 'b@x.test']);

    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'customers', 'subject' => 'Hi', 'body' => 'Hello'])
        ->assertRedirect();

    $campaign = MailCampaign::query()->firstOrFail();
    // sync queue + faked mailer → the jobs ran inline and marked each row sent.
    expect($campaign->recipients()->count())->toBe(2)
        ->and($campaign->recipients()->where('status', 'sent')->count())->toBe(2)
        ->and($campaign->fresh()->sent_count)->toBe(2)
        ->and($campaign->fresh()->failed_count)->toBe(0)
        ->and($campaign->recipient_count)->toBe(2);
});

test('a terminal delivery failure is recorded on the recipient and tallied', function () {
    $campaign = MailCampaign::create([
        'created_by' => $this->admin->id, 'subject' => 'S', 'body' => 'B', 'audience' => 'customers',
        'recipient_count' => 1, 'sent_count' => 0, 'failed_count' => 0, 'sent_at' => now(),
    ]);
    $recipient = $campaign->recipients()->create(['email' => 'x@x.test', 'name' => 'X', 'status' => 'queued']);

    $job = new SendCampaignEmail('x@x.test', 'S', 'B', 'X', null, $this->tenant->id, $recipient->id);
    $job->failed(new RuntimeException('SMTP connection refused'));

    expect($recipient->fresh()->status)->toBe('failed')
        ->and($recipient->fresh()->error)->toContain('SMTP connection refused')
        ->and($campaign->fresh()->failed_count)->toBe(1)
        ->and($campaign->fresh()->sent_count)->toBe(0);
});
