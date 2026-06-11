<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\BillingService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Public, signed "pay your bill" link (no login) — emailed to customers. The
 * signature authenticates the link; we look the customer up scope-free, bind
 * their tenant, and start a Stripe Checkout for the outstanding balance. The
 * existing webhook settles the balance on completion.
 */
class PublicPayController extends Controller
{
    public function pay(Request $request, string $customer, BillingService $billing, StripeService $stripe): RedirectResponse
    {
        $record = Customer::withoutGlobalScopes()->find((int) $customer);
        abort_if($record === null, 404);

        app()->instance('tenant_id', $record->getAttribute('tenant_id'));
        $amount = $billing->outstandingForCustomer($record);
        if ($amount <= 0) {
            return redirect('/pay/thanks');
        }

        $url = $stripe->createBalanceCheckout(
            $record,
            $amount,
            url('/pay/thanks'),
            URL::signedRoute('pay.link', ['customer' => $record->id]),
        );
        abort_if($url === null, 503, 'Online payment is unavailable right now — please contact your service company.');

        return redirect()->away($url);
    }
}
