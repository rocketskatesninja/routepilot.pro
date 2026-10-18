<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordPayment;
use App\Actions\SendInvoice;
use App\Actions\VoidInvoice;
use App\Http\Requests\RecordPaymentRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $invoice->load(['customer', 'charges']);
        $tenant = app()->has('tenant') ? app('tenant') : null;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant instanceof Tenant ? $tenant : null,
        ]);

        return $pdf->download("invoice-{$invoice->number}.pdf");
    }

    /** Email the invoice (branded PDF + signed pay link) to the customer. */
    public function email(Request $request, Invoice $invoice, SendInvoice $send): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $email = $send->handle($invoice);
        if ($email === null) {
            return back()->with('error', $invoice->status === 'void' ? 'A voided invoice can’t be sent.' : 'This customer has no email address on file.');
        }

        return back()->with('success', "Invoice {$invoice->number} emailed to {$email}.");
    }

    /** Record an off-platform payment against this invoice — all that's left on it, or part. */
    public function markPaid(RecordPaymentRequest $request, Invoice $invoice, RecordPayment $record): RedirectResponse
    {
        $data = $request->validated();
        $method = (string) $data['method'];

        $payment = $record->handle($invoice->customer()->firstOrFail(), $method, $request->user()->id,
            amount: isset($data['amount']) ? (float) $data['amount'] : null, invoice: $invoice, reference: $request->reference());
        if ($payment === null) {
            return back()->with('error', "Nothing is left to pay on invoice {$invoice->number}.");
        }
        AuditLog::record($request->user(), 'payment.recorded', $payment, ['invoice' => $invoice->number, 'amount' => (float) $payment->amount, 'method' => $method]);

        $left = $invoice->refresh()->balance();

        return back()->with('success', $left > 0
            ? 'Payment of $'.number_format((float) $payment->amount, 2)." recorded on {$invoice->number}. \$".number_format($left, 2).' is left on it.'
            : "Invoice {$invoice->number} is paid in full.");
    }

    /** Cancel a bill — the invoice and its charges are voided, so they're no longer owed. */
    public function void(Request $request, Invoice $invoice, VoidInvoice $action): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (! $action->handle($invoice, $request->user())) {
            return back()->with('error', "Invoice {$invoice->number} has payments on it, so it can’t be voided.");
        }
        AuditLog::record($request->user(), 'invoice.voided', $invoice, ['total' => (float) $invoice->total]);

        return back()->with('success', "Invoice {$invoice->number} voided.");
    }
}
