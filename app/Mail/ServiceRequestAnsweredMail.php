<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use App\Support\EmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emailed to a customer when the company answers their request. Sent by
 * SendRequestAnswer, which skips opted-out customers.
 */
class ServiceRequestAnsweredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $company,
        public string $headline,
        public string $summary,
        public ?string $reply,
        public bool $declined,
        public ?string $portalUrl,
        /** The company whose colours and logo the email wears (null = RoutePilot's own). */
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.' — '.$this->company);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.request-answered', with: ['brand' => EmailBrand::for($this->tenant)]);
    }
}
