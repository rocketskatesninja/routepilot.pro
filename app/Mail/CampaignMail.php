<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use App\Support\EmailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * A single campaign email. Body is treated as plain text (escaped) for
 * safety; a CAN-SPAM unsubscribe footer is appended for customer recipients.
 * The From comes from the tenant's mail config (null = platform default).
 */
class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{address: string, name: string}|null  $fromAddress
     */
    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $recipientName,
        public ?string $unsubscribeUrl,
        public ?array $fromAddress = null,
        /** The company sending it (its colours and logo); null for RoutePilot's own broadcasts. */
        public ?Tenant $tenant = null,
        /** Where the inbox's own "Unsubscribe" button posts (customer recipients only). */
        public ?string $oneClickUrl = null,
    ) {}

    public function headers(): Headers
    {
        return new Headers(text: $this->oneClickUrl !== null
            ? ['List-Unsubscribe' => '<'.$this->oneClickUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']
            : []);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: $this->fromAddress !== null ? new Address($this->fromAddress['address'], $this->fromAddress['name']) : null,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.campaign', with: ['brand' => EmailBrand::for($this->tenant)]);
    }
}
