<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Transactional dunning notice when a customer's autopay charge is declined —
 * with a link to pay manually. Sent regardless of opt-out.
 */
class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public float $amount,
        public string $companyName,
        public string $payUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment issue — '.$this->companyName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-failed');
    }
}
