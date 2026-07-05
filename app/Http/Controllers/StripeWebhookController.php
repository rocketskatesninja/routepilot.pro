<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SettleCheckoutSession;
use App\Models\StripeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Stripe webhook receiver. Fails closed: a request without a valid signature
 * (or with no configured secret) is rejected. Events are idempotent — a
 * redelivery is acknowledged but not re-processed. Concrete event handlers
 * (payment_intent.succeeded → settle invoice, etc.) layer on once real keys
 * are configured.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, SettleCheckoutSession $settle): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $payload = (string) $request->getContent();

        if ($secret === '' || ! $this->signatureValid($payload, (string) $request->header('Stripe-Signature', ''), $secret)) {
            abort(400, 'Invalid signature.');
        }

        $event = json_decode($payload, true);
        $event = is_array($event) ? $event : [];
        $eventId = is_string($event['id'] ?? null) ? $event['id'] : '';
        if ($eventId === '') {
            abort(400, 'Missing event id.');
        }

        if (StripeEvent::query()->where('event_id', $eventId)->exists()) {
            return response('', 200); // already processed — idempotent
        }

        // Record + settle in one transaction so a settle() failure rolls back the
        // event record and Stripe legitimately retries (instead of the redelivery
        // being skipped as "already processed" with the payment lost).
        DB::transaction(function () use ($event, $eventId, $settle): void {
            StripeEvent::create([
                'event_id' => $eventId,
                'type' => is_string($event['type'] ?? null) ? $event['type'] : 'unknown',
            ]);

            $this->settle($event, $settle);
        });

        return response('', 200);
    }

    /**
     * On a completed Checkout payment, settle it against the customer's balance
     * (shared with the reconciliation job, which is idempotent on the intent id).
     *
     * @param  array<string, mixed>  $event
     */
    private function settle(array $event, SettleCheckoutSession $settle): void
    {
        if (($event['type'] ?? null) !== 'checkout.session.completed') {
            return;
        }

        $data = $event['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;
        if (is_array($object)) {
            $settle->handle($object);
        }
    }

    /** Verify Stripe's `t=…,v1=…` HMAC-SHA256 signature header. */
    private function signatureValid(string $payload, string $header, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            $pair = explode('=', $kv, 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if ($timestamp === null || $signature === null) {
            return false;
        }

        // Reject stale timestamps (replay window) — 5-minute tolerance, matching Stripe's SDK.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }
}
