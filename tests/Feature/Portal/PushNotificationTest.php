<?php

declare(strict_types=1);

use App\Jobs\SendWebPush;
use App\Models\Customer;
use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

beforeEach(function () {
    config(['services.webpush.public_key' => 'test-public-key']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();

    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
});

function phone(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPubKey', 'auth' => 'authSecret']];
}

test('a user turns notifications on for their phone', function () {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone())->assertRedirect()->assertSessionHasNoErrors();

    $saved = $this->admin->pushSubscriptions()->sole();
    expect($saved->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/abc123')
        ->and($saved->public_key)->toBe('BPubKey')
        ->and($saved->auth_token)->toBe('authSecret');
});

test('only real push services are accepted as an endpoint', function (string $endpoint) {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone($endpoint))->assertSessionHasErrors('endpoint');

    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'plain http' => 'http://fcm.googleapis.com/fcm/send/x',
    'internal host' => 'https://127.0.0.1/x',
    'metadata host' => 'https://169.254.169.254/latest',
    'look-alike suffix' => 'https://fcm.googleapis.com.evil.example/x',
    'userinfo trick' => 'https://fcm.googleapis.com@evil.example/x',
]);

test('Firefox, Safari and Edge push services are accepted', function (string $endpoint) {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone($endpoint))->assertSessionHasNoErrors();
})->with([
    'https://updates.push.services.mozilla.com/wpush/v2/abc',
    'https://web.push.apple.com/abc',
    'https://wns2-bl2p.notify.windows.com/w/?token=abc',
]);

test('a phone someone else used moves to whoever turns it on', function () {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone())->assertRedirect();
    $this->actingAs($this->user)->post('/push-subscriptions', phone())->assertRedirect();

    expect(PushSubscription::query()->sole()->user_id)->toBe($this->user->id);
});

test('an impersonator cannot sign their phone up for the user’s notifications', function () {
    $this->actingAs($this->user)->withSession(['impersonator_id' => $this->admin->id])
        ->post('/push-subscriptions', phone())->assertForbidden();

    expect(PushSubscription::query()->count())->toBe(0);
});

test('a user turns their phone off, and cannot turn off anyone else’s', function () {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone())->assertRedirect();

    $this->actingAs($this->user)->delete('/push-subscriptions', ['endpoint' => phone()['endpoint']])->assertRedirect();
    expect(PushSubscription::query()->count())->toBe(1);

    $this->actingAs($this->admin)->delete('/push-subscriptions', ['endpoint' => phone()['endpoint']])->assertRedirect();
    expect(PushSubscription::query()->count())->toBe(0);
});

test('a notification goes to the user’s phones, with its title, text and link', function () {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone())->assertRedirect();
    Queue::fake();

    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Algae bloom'])->assertRedirect();

    $saved = $this->admin->notifications()->sole();
    Queue::assertPushed(SendWebPush::class, fn (SendWebPush $job) => $job->userId === $this->admin->id
        && $job->message['title'] === $saved->data['title']
        && $job->message['url'] === '/notifications/'.$saved->id.'/open'
        && $job->message['tag'] === $saved->id);
});

test('no push for a type the user turned off, or for a user with no phone', function () {
    $this->actingAs($this->admin)->post('/push-subscriptions', phone())->assertRedirect();
    $this->actingAs($this->admin)->patch('/notifications/types', ['service_request' => false])->assertRedirect();
    $other = User::factory()->for($this->tenant)->create(); // a second admin, no phone
    Queue::fake();

    $this->actingAs($this->user)->post('/requests', ['type' => 'other', 'message' => 'Help'])->assertRedirect();

    expect($other->notifications()->count())->toBe(1);
    Queue::assertNotPushed(SendWebPush::class);
});

test('the job sends to each phone and forgets the ones the push service says are gone', function () {
    $keep = PushSubscription::query()->make(['endpoint' => 'https://fcm.googleapis.com/fcm/send/keep', 'public_key' => 'k', 'auth_token' => 'a']);
    $keep->forceFill(['user_id' => $this->admin->id])->save();
    $gone = PushSubscription::query()->make(['endpoint' => 'https://fcm.googleapis.com/fcm/send/gone', 'public_key' => 'k', 'auth_token' => 'a']);
    $gone->forceFill(['user_id' => $this->admin->id])->save();

    $push = Mockery::mock(WebPush::class);
    $push->shouldReceive('queueNotification')->twice();
    $push->shouldReceive('flush')->once()->andReturnUsing(function () {
        yield new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/fcm/send/keep'), new Response(201));
        yield new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/fcm/send/gone'), new Response(410), false, 'Gone');
    });

    (new SendWebPush($this->admin->id, ['title' => 'T', 'body' => 'B', 'url' => '/x', 'tag' => 'id']))->handle($push);

    expect(PushSubscription::query()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/keep']);
});

test('the notifications page hands the phone the public key', function () {
    $this->actingAs($this->admin)->get('/notifications')
        ->assertInertia(fn ($page) => $page->where('pushKey', 'test-public-key'));
});
