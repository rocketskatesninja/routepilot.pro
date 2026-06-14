<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\MarkInvoicePaid;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Branded PDF invoices. The {invoice} binding is tenant-scoped, so staff only
 * resolve their own tenant's invoices; a customer is additionally checked to
 * own the invoice.
 */
class InvoiceController extends Controller
{
    public function download(Request $request, Invoice $invoice): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->role === 'customer') {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            abort_unless($customer !== null && $invoice->getAttribute('customer_id') === $customer->id, 403);
        } else {
            abort_unless(in_array($user->role, ['tenant_admin', 'agent'], true), 403);
        }

        $invoice->load(['customer', 'lineItems']);
        $tenant = app()->has('tenant') ? app('tenant') : null;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant instanceof Tenant ? $tenant : null,
        ]);

        return $pdf->download("invoice-{$invoice->number}.pdf");
    }

    /** Email the invoice (branded PDF + signed pay link) to the customer. */
    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $invoice->load('customer');
        $email = $invoice->customer->email;
        if (! is_string($email) || $email === '') {
            return back()->with('error', 'This customer has no email address on file.');
        }

        $payUrl = URL::signedRoute('pay.link', ['customer' => $invoice->getAttribute('customer_id')]);
        Mail::to($email)->queue(new InvoiceMail($invoice, $payUrl));

        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        return back()->with('success', 'Invoice emailed to '.$email.'.');
    }

    /** Record an off-platform payment that settles this invoice in full. */
    public function markPaid(Request $request, Invoice $invoice, MarkInvoicePaid $action): RedirectResponse
    {
        abort_unless($request->user()?->role === 'tenant_admin', 403);

        $method = (string) $request->string('method');
        if (! in_array($method, ['cash', 'check', 'card', 'ach', 'other'], true)) {
            $method = 'other';
        }

        $action->handle($invoice, $method, $request->user()->id);

        return back()->with('success', "Invoice {$invoice->number} marked paid.");
    }
}
