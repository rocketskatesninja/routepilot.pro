<?php

declare(strict_types=1);

use App\Models\StripeEvent;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
});

function signedCall(string $payload): TestResponse
{
    $timestamp = '1700000000';
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

    return test()->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

test('an unsigned webhook is rejected (fails closed)', function () {
    $this->postJson('/stripe/webhook', ['id' => 'evt_1', 'type' => 'payment_intent.succeeded'])
        ->assertStatus(400);

    expect(StripeEvent::query()->count())->toBe(0);
});

test('a webhook is rejected when no signing secret is configured', function () {
    config(['services.stripe.webhook_secret' => '']);

    signedCall('{"id":"evt_2","type":"x"}')->assertStatus(400);
});

test('a validly-signed webhook is recorded once (idempotent)', function () {
    $payload = json_encode(['id' => 'evt_123', 'type' => 'payment_intent.succeeded']);

    signedCall($payload)->assertOk();
    expect(StripeEvent::query()->where('event_id', 'evt_123')->count())->toBe(1);

    // Redelivery of the same event must not double-process.
    signedCall($payload)->assertOk();
    expect(StripeEvent::query()->where('event_id', 'evt_123')->count())->toBe(1);
});

test('a signed webhook with a tampered payload is rejected', function () {
    $timestamp = '1700000000';
    $signature = hash_hmac('sha256', $timestamp.'.'.'{"id":"evt_real"}', 'whsec_test');

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], '{"id":"evt_tampered"}')->assertStatus(400);
});
