<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Email a customer their invoice: the branded PDF plus a signed "pay online"
 * link (no login needed). A draft becomes "sent" only once it has actually gone
 * out. Returns the address it went to, or null when nothing was sent: the
 * invoice is void, or the customer has no email address on file (it stays a
 * draft).
 */
class SendInvoice
{
    public function handle(Invoice $invoice): ?string
    {
        $email = $invoice->customer()->value('email');
        if ($invoice->status === 'void' || ! is_string($email) || $email === '') {
            return null;
        }

        $payUrl = URL::signedRoute('pay.link', ['customer' => $invoice->getAttribute('customer_id')]);
        Mail::to($email)->queue(new InvoiceMail($invoice, $payUrl));

        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        return $email;
    }
}
