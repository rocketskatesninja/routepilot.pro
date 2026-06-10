<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StripeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Stripe webhook receiver. Fails closed: a request without a valid signature
 * (or with no configured secret) is rejected. Events are idempotent — a
 * redelivery is acknowledged but not re-processed. Concrete event handlers
 * (payment_intent.succeeded → settle invoice, etc.) layer on once real keys
 * are configured.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
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

        StripeEvent::create([
            'event_id' => $eventId,
            'type' => is_string($event['type'] ?? null) ? $event['type'] : 'unknown',
        ]);

        // TODO (needs live keys): dispatch on $event['type'] —
        // payment_intent.succeeded → mark Payment/Invoice paid; charge.refunded → …

        return response('', 200);
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

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }
}
