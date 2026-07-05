<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SettleCheckoutSession;
use App\Services\StripeService;
use Illuminate\Console\Command;

/**
 * Safety net for Stripe Checkout payments: scan recently-paid sessions and
 * settle any the `checkout.session.completed` webhook never delivered (endpoint
 * down, signing-secret drift, network drop). SettleCheckoutSession is idempotent
 * on the PaymentIntent, so sessions the webhook already handled are skipped —
 * the card is charged once, but the balance always clears.
 */
class ReconcileStripePayments extends Command
{
    protected $signature = 'app:reconcile-stripe-payments {--hours=72 : How far back to scan}';

    protected $description = 'Settle paid Stripe Checkout sessions a webhook may have missed';

    public function handle(StripeService $stripe, SettleCheckoutSession $settle): int
    {
        if (! $stripe->configured()) {
            $this->warn('Stripe is not configured — skipping reconciliation.');

            return self::SUCCESS;
        }

        $settled = 0;
        foreach ($stripe->recentCheckoutSessions((int) $this->option('hours')) as $session) {
            if ($settle->handle($session) !== null) {
                $settled++;
            }
        }

        $this->info("Reconcile: {$settled} orphaned payment(s) settled.");

        return self::SUCCESS;
    }
}
