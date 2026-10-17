<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\EmailBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emails a customer their invoice (branded PDF attached) with a one-click,
 * signed "pay online" link that starts a Stripe Checkout — no login required.
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public string $payUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invoice '.$this->invoice->number.' — '.$this->invoice->customer->tenant->name);
    }

    public function content(): Content
    {
        $customer = $this->invoice->customer;

        return new Content(view: 'emails.invoice', with: [
            'brand' => EmailBrand::for($customer->tenant),
            'customerName' => $customer->first_name ?: $customer->displayName(),
            'invoice' => $this->invoice->load('charges'),
            'dueOn' => $this->invoice->due_at?->format('l, F j'),
            'payUrl' => $this->payUrl,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $invoice = $this->invoice->load(['customer', 'charges']);
        $tenant = $invoice->customer->tenant;
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant instanceof Tenant ? $tenant : null,
        ]);

        return [
            Attachment::fromData(fn (): string => (string) $pdf->output(), 'invoice-'.$invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
