<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\PaymentFailedMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\AutopayDeclined;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Charge one autopay customer's saved card off-session. On success, settle (via
 * RecordPayment). On decline, run dunning: record a failed Payment and — on the
 * month's FIRST decline only (no spam on retries) — email the customer a pay
 * link and alert the tenant admins. (Whether a bill is overdue is about its due
 * date, not the card, so invoices are left alone.)
 *
 * The dunning cycle is the calendar month: one charge on the 2nd, then
 * app:retry-autopay tries again daily until MAX_ATTEMPTS declines.
 */
class ChargeAutopayCustomer
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private BillingService $billing,
        private StripeService $stripe,
        private RecordPayment $recordPayment,
    ) {}

    /** @return 'charged'|'declined'|'skipped' */
    public function handle(Customer $customer): string
    {
        $amount = $this->billing->outstandingForCustomer($customer);
        if ($amount <= 0) {
            return 'skipped';
        }

        $stripeCustomer = $customer->getAttribute('stripe_customer_id');
        $pm = PaymentMethod::query()->find($customer->getAttribute('default_payment_method_id'));
        if (! is_string($stripeCustomer) || $pm === null) {
            return 'skipped';
        }

        $result = $this->stripe->chargeOffSession(
            $stripeCustomer,
            (string) $pm->getAttribute('stripe_payment_method_id'),
            $amount,
            (int) $customer->id,
            (int) $customer->getAttribute('tenant_id'),
            $this->stripe->connectAccountFor($customer),
            // Stable per customer per day: a same-day re-run (double-fired
            // schedule, manual re-invoke) replays the original charge rather
            // than billing the card again; the next day's dunning retry uses a
            // fresh key so a genuine decline can be re-attempted.
            sprintf('autopay:%d:%s', (int) $customer->id, now()->format('Y-m-d')),
        );

        if ($result !== null && $result['status'] === 'succeeded') {
            $this->recordPayment->handle($customer, 'card', null, $result['id'], $amount); // exactly what the card was charged

            return 'charged';
        }

        $this->dun($customer, $amount, $result['id'] ?? null);

        return 'declined';
    }

    /**
     * This month's declined autopay attempts for the customer, newest first.
     *
     * @return Collection<int, Payment>
     */
    public function declinesThisMonth(Customer $customer): Collection
    {
        return Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->latest()->latest('id')
            ->get();
    }

    private function dun(Customer $customer, float $amount, ?string $paymentIntentId): void
    {
        $firstFailure = $this->declinesThisMonth($customer)->isEmpty();

        Payment::create([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'status' => 'failed',
            'method' => 'card',
            'stripe_payment_intent_id' => $paymentIntentId,
            'failure_reason' => 'Autopay charge declined',
        ]);

        if (! $firstFailure) {
            return; // already alerted this month — don't spam on retries
        }

        $email = $customer->email;
        if (is_string($email) && $email !== '') {
            $payUrl = URL::signedRoute('pay.link', ['customer' => $customer->id]);
            Mail::to($email)->queue(new PaymentFailedMail($customer->first_name ?: $customer->displayName(), $amount, $customer->tenant->name, $payUrl, $customer->tenant));
        }

        $admins = User::query()
            ->where('tenant_id', $customer->getAttribute('tenant_id'))
            ->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new AutopayDeclined($customer, $amount));
    }
}
