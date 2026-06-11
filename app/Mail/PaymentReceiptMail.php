<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional receipt sent to a customer when a payment settles. Not a
 * marketing message — sent regardless of email opt-out.
 */
class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public float $amount,
        public string $companyName,
        public string $paidOn,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment received — '.$this->companyName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-receipt');
    }
}
