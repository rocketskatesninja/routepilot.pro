<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Customer portal — the homeowner's own service history. Strictly scoped to
 * the logged-in customer's pools (a customer never sees another's visits).
 */
class PortalController extends Controller
{
    public function history(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);
        $poolIds = $customer->pools()->pluck('id');

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $poolIds)
            ->where('status', 'completed')
            ->with('pool:id,name,photo_path')
            ->latest('completed_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (ServiceVisit $v): array {
                $poolPhoto = $v->pool?->getAttribute('photo_path');

                return [
                    'id' => $v->id,
                    'pool' => $v->pool?->getAttribute('name'),
                    'pool_photo' => $this->photoUrl($poolPhoto),
                    'on' => $v->completed_at?->toDateString(),
                ];
            });

        $selected = null;
        $selectedId = $request->integer('selected');
        if ($selectedId > 0) {
            $visit = ServiceVisit::query()
                ->whereIn('pool_id', $poolIds)
                ->whereKey($selectedId)
                ->with(['pool:id,name', 'agent:id,first_name,last_name', 'chemicalReading', 'treatments', 'tasks', 'photos'])
                ->first();
            if ($visit !== null) {
                $selected = $this->toDetail($visit);
            }
        }

        return Inertia::render('portal/History', [
            'visits' => $visits,
            'selected' => $selected,
        ]);
    }

    public function requests(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);

        $requests = ServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->with('pool:id,name')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ServiceRequest $r): array => [
                'id' => $r->id,
                'type' => $r->type,
                'message' => $r->message,
                'status' => $r->status,
                'pool' => $r->pool?->getAttribute('name'),
                'preferred_date' => $r->preferred_date?->toDateString(),
                'on' => $r->created_at?->toDateString(),
            ])->all();

        $pools = $customer->pools()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p): array => ['id' => $p->id, 'name' => $p->getAttribute('name')])->all();

        return Inertia::render('portal/Requests', [
            'requests' => $requests,
            'pools' => $pools,
        ]);
    }

    public function balance(Request $request, BillingService $billing, StripeService $stripe): Response
    {
        $customer = $this->resolveCustomer($request);
        $breakdown = $billing->breakdownForCustomer($customer);

        $card = null;
        $pmId = $customer->getAttribute('default_payment_method_id');
        if ($pmId !== null) {
            $pm = PaymentMethod::query()->find($pmId);
            if ($pm !== null) {
                $card = ['brand' => $pm->brand, 'last4' => $pm->last4];
            }
        }

        return Inertia::render('portal/Balance', [
            'total' => $breakdown['total'],
            'visits' => $breakdown['visits'],
            'charges' => $breakdown['charges'],
            'can_pay' => $stripe->configured(),
            'paid' => $request->boolean('paid'),
            'autopay' => (bool) $customer->getAttribute('autopay_enabled'),
            'card' => $card,
        ]);
    }

    /** Start a Stripe Checkout for the outstanding balance; redirects to Stripe. */
    public function pay(Request $request, BillingService $billing, StripeService $stripe): SymfonyResponse
    {
        $customer = $this->resolveCustomer($request);
        $amount = $billing->outstandingForCustomer($customer);
        if ($amount <= 0) {
            return back()->with('error', 'Your balance is already clear.');
        }

        $url = $stripe->createBalanceCheckout($customer, $amount, url('/balance?paid=1'), url('/balance'));
        if ($url === null) {
            return back()->with('error', 'Online payment is unavailable right now — please contact your service company.');
        }

        return Inertia::location($url);
    }

    /** Save a card for autopay via a hosted setup Checkout; redirects to Stripe. */
    public function setupAutopay(Request $request, StripeService $stripe): SymfonyResponse
    {
        $customer = $this->resolveCustomer($request);
        $url = $stripe->createSetupCheckout($customer, url('/autopay/complete').'?session_id={CHECKOUT_SESSION_ID}', url('/balance'));
        if ($url === null) {
            return back()->with('error', 'Card setup is unavailable right now.');
        }

        return Inertia::location($url);
    }

    /** Stripe redirects here after a card is saved — store it + enable autopay. */
    public function autopayComplete(Request $request, StripeService $stripe): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        $sessionId = (string) $request->query('session_id');
        $card = $sessionId !== '' ? $stripe->retrieveSetupCard($sessionId) : null;
        // session_id is user-supplied: only accept a session we stamped for THIS
        // customer, so a card from someone else's setup session can't be attached.
        if ($card === null || ($card['customer_id'] ?? null) !== $customer->id) {
            return redirect('/balance')->with('error', 'Your card was not saved — please try again.');
        }

        $pm = PaymentMethod::create([
            'customer_id' => $customer->id,
            'stripe_payment_method_id' => $card['payment_method'],
            'brand' => $card['brand'],
            'last4' => $card['last4'],
            'exp_month' => $card['exp_month'],
            'exp_year' => $card['exp_year'],
            'is_default' => true,
        ]);
        $customer->forceFill(['autopay_enabled' => true, 'default_payment_method_id' => $pm->id])->save();

        return redirect('/balance')->with('success', 'Autopay is on — card ending '.($card['last4'] ?? '••••').' saved.');
    }

    public function disableAutopay(Request $request): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        $customer->forceFill(['autopay_enabled' => false])->save();

        return back()->with('success', 'Autopay turned off.');
    }

    /** The customer record for the signed-in portal user (or 403/404). */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_unless($user?->role === 'customer', 403);

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404);

        return $customer;
    }

    /** @return array<string, mixed> */
    private function toDetail(ServiceVisit $visit): array
    {
        $reading = $visit->chemicalReading;

        return [
            'id' => $visit->id,
            'pool' => $visit->pool?->getAttribute('name'),
            'pool_id' => $visit->pool?->getKey(),
            'on' => $visit->completed_at?->toDateString(),
            'agent' => $visit->agent?->displayName(),
            'notes' => $visit->notes,
            'reading' => $reading !== null ? [
                'free_chlorine' => $reading->free_chlorine,
                'ph' => $reading->ph,
                'alkalinity' => $reading->alkalinity,
                'calcium_hardness' => $reading->calcium_hardness,
                'cyanuric_acid' => $reading->cyanuric_acid,
                'salt' => $reading->salt,
                'lsi_score' => $reading->lsi_score,
            ] : null,
            'treatments' => $visit->treatments->map(fn ($t): array => [
                'name' => $t->getAttribute('chemical_name'),
                'amount' => (float) $t->amount,
                'unit' => $t->getAttribute('unit'),
            ])->all(),
            'tasks' => $visit->tasks->map(fn ($t): array => [
                'name' => $t->getAttribute('task_name'),
                'done' => (bool) $t->getAttribute('is_completed'),
            ])->all(),
            'photos' => $visit->photos->map(fn ($p): ?string => $this->photoUrl($p->getAttribute('photo_path')))->all(),
        ];
    }
}
