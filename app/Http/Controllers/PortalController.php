<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Services\BillingService;
use App\Services\StripeService;
use App\Support\PortalVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Customer portal — the homeowner's own service history. Strictly scoped to
 * the logged-in customer's pools (a customer never sees another's visits).
 */
class PortalController extends Controller
{
    public function history(Request $request): Response|RedirectResponse
    {
        // Staff following a customer's report link get the office copy of it.
        if ($request->user()?->isStaff()) {
            return redirect()->route('reports.index', array_filter(['selected' => $request->integer('selected')]));
        }

        $customer = $this->resolveCustomer($request);

        $visits = ServiceVisit::query()
            ->whereIn('pool_id', $customer->pools()->pluck('id'))
            ->where('status', 'completed')
            ->with(PortalVisit::WITH)
            ->latest('completed_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ServiceVisit $v): array => PortalVisit::toArray($v));

        return Inertia::render('portal/History', ['visits' => $visits]);
    }

    /** The homeowner's pools — what each one is, how its water looked, and the service it gets. */
    public function pools(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);

        $pools = $customer->pools()->with('serviceLocation')->orderBy('name')->get()->map(function (Pool $pool): array {
            // Newest first — the latest reading, plus the recent trend for chlorine and pH.
            $history = $pool->visits()->where('status', 'completed')->latest('completed_at')->with('chemicalReading')->limit(8)->get();
            $tested = $history->first(fn (ServiceVisit $v): bool => $v->chemicalReading !== null);
            $plans = $pool->subscriptions()->current()->with(['serviceType:id,name', 'agent:id,first_name,last_name'])->get();

            return [
                'id' => $pool->id,
                'name' => $pool->name,
                'address' => implode(', ', array_filter([$pool->serviceLocation?->address_line1, $pool->serviceLocation?->city])) ?: null,
                'photo' => $this->photoUrl($pool->getAttribute('photo_path')),
                'type' => $pool->type,
                'sanitizer' => $pool->sanitizer_type,
                'volume' => $pool->volume_gallons,
                'features' => array_keys(array_filter([
                    'heater' => $pool->has_heater, 'cover' => $pool->has_cover, 'automation' => $pool->has_automation,
                    'cleaner' => $pool->has_pool_cleaner, 'water_feature' => $pool->has_water_feature, 'auto_fill' => $pool->has_auto_fill,
                ])),
                'last_serviced' => Tenant::localDate($history->first()?->completed_at),
                'tested_on' => Tenant::localDate($tested?->completed_at),
                'reading' => $tested !== null ? PortalVisit::reading($tested) : null,
                'targets' => $pool->custom_target_ranges,
                'trend' => ServiceVisit::chemistryTrend($history->reverse()->values()),
                'plans' => $plans->map(fn (ServiceSubscription $p): array => [
                    'id' => $p->id,
                    'service' => $p->serviceType?->name,
                    'frequency' => $p->frequency,
                    'preferred_day' => $p->preferred_day,
                    'day_of_month' => $p->day_of_month,
                    'starts_on' => $p->starts_on?->toDateString(),
                    'agent' => $p->agent?->firstName(),
                    'status' => $p->status,
                ])->all(),
            ];
        })->all();

        return Inertia::render('portal/Pools', ['pools' => $pools]);
    }

    public function requests(Request $request): Response
    {
        $customer = $this->resolveCustomer($request);

        $requests = ServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->with(['pool:id,name', 'serviceType:id,name', 'plan.serviceType:id,name'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (ServiceRequest $r): array => [
                'id' => $r->id,
                'type' => $r->type,
                'title' => $r->title(),
                'message' => $r->message,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'reply' => $r->reply,
                'plan' => $r->plan?->scheduleLabel(),
                'pool' => $r->pool?->getAttribute('name'),
                'preferred_date' => $r->preferred_date?->toDateString(),
                'ends_on' => $r->ends_on?->toDateString(),
                'on' => Tenant::localDate($r->created_at),
            ])->all();

        // What they have now — each can be paused or stopped on its own.
        $today = Tenant::localToday();
        $pending = ServiceRequest::query()->where('customer_id', $customer->id)->where('status', 'pending')
            ->whereIn('type', ['hold', 'cancel'])->whereNotNull('service_subscription_id')
            ->pluck('type', 'service_subscription_id');
        $plans = ServiceSubscription::query()
            ->whereIn('pool_id', $customer->pools()->pluck('id'))
            ->current()
            ->with(['serviceType', 'agent:id,first_name,last_name', 'pool:id,name'])
            ->get()
            ->sortBy(fn (ServiceSubscription $p): string => ($p->pool?->getAttribute('name') ?? '').'|'.($p->serviceType->name ?? ''))
            ->values();

        // Each plan's next visit on the route (holds are already left off the schedule).
        $nextVisits = RouteStop::query()
            ->whereIn('route_stops.service_subscription_id', $plans->pluck('id'))
            ->where('route_stops.status', 'pending')
            ->join('routes', 'routes.id', '=', 'route_stops.route_id')
            ->where('routes.scheduled_date', '>=', $today->toDateString())
            ->groupBy('route_stops.service_subscription_id')
            ->selectRaw('route_stops.service_subscription_id as plan_id, min(routes.scheduled_date) as next_date')
            ->pluck('next_date', 'plan_id');

        $plans = $plans
            ->map(function (ServiceSubscription $p) use ($pending, $today, $nextVisits): array {
                $onHold = $p->hold_ends_at !== null && $p->hold_ends_at->gte($today);

                return [
                    'id' => $p->id,
                    'pool_id' => $p->getAttribute('pool_id'),
                    'pool' => $p->pool?->getAttribute('name'),
                    'service_type_id' => $p->getAttribute('service_type_id'),
                    'service' => $p->serviceType?->name,
                    'frequency' => $p->frequency,
                    'preferred_day' => $p->preferred_day,
                    'day_of_month' => $p->day_of_month,
                    'starts_on' => $p->starts_on?->toDateString(),
                    'ends_on' => $p->ends_on?->toDateString(),
                    'one_time' => $p->frequency === 'one_time',
                    'modules' => $p->serviceType !== null ? array_keys(array_filter($p->serviceType->modules())) : [],
                    'next_visit' => isset($nextVisits[$p->id]) ? substr((string) $nextVisits[$p->id], 0, 10) : null,
                    'agent' => $p->agent?->firstName(),
                    'status' => $p->status,
                    'hold_from' => $onHold ? $p->hold_starts_at?->toDateString() : null,
                    'hold_to' => $onHold ? $p->hold_ends_at->toDateString() : null,
                    'pending' => $pending[$p->id] ?? null,
                ];
            })->all();

        // What the company offers customers (active + "customers can request this").
        $services = ServiceType::query()->where('is_active', true)->where('requestable', true)->orderBy('price')->get()
            ->map(function (ServiceType $t): array {
                $modules = $t->modules();

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                    'frequency' => $t->frequency,
                    'price' => (float) $t->price,
                    'minutes' => $t->estimated_duration_minutes,
                    // The checklist only when the tech actually works through it on the visit.
                    'tasks' => $modules['tasks'] ? array_values($t->tasks ?? []) : [],
                    'modules' => array_keys(array_filter($modules)),
                ];
            })->all();

        $pools = $customer->pools()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p): array => ['id' => $p->id, 'name' => $p->getAttribute('name')])
            ->all();

        return Inertia::render('portal/Requests', [
            'requests' => $requests,
            'plans' => $plans,
            'services' => $services,
            'pools' => $pools,
            'today' => $today->toDateString(),
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

        $payments = Payment::query()->where('customer_id', $customer->id)->where('status', 'succeeded')
            ->latest('paid_at')->limit(6)->get()
            ->map(fn (Payment $p): array => ['amount' => (float) $p->amount, 'on' => Tenant::localDate($p->paid_at), 'method' => $p->method])
            ->all();

        return Inertia::render('portal/Balance', [
            'total' => $breakdown['total'],
            'payments' => $payments,
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

        $pm = DB::transaction(function () use ($customer, $card): PaymentMethod {
            // Demote any prior card so exactly one stays flagged default.
            PaymentMethod::query()->where('customer_id', $customer->id)
                ->where('is_default', true)->update(['is_default' => false]);

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

            return $pm;
        });
        AuditLog::record($request->user(), 'autopay.enabled', $customer, ['last4' => $card['last4'] ?? null]);

        return redirect('/balance')->with('success', 'Autopay is on — card ending '.($card['last4'] ?? '••••').' saved.');
    }

    public function disableAutopay(Request $request): RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        $customer->forceFill(['autopay_enabled' => false])->save();
        AuditLog::record($request->user(), 'autopay.disabled', $customer);

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
}
