<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: $this->fromAddress !== null ? new Address($this->fromAddress['address'], $this->fromAddress['name']) : null,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->buildHtml());
    }

    private function buildHtml(): string
    {
        $unsubscribe = $this->unsubscribeUrl !== null
            ? '<p style="margin-top:28px;font-size:12px;color:#888;">No longer want these? <a href="'.e($this->unsubscribeUrl).'">Unsubscribe</a>.</p>'
            : '';

        return '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:0 auto;color:#222;">'
            .'<p>Hi '.e($this->recipientName).',</p>'
            .'<div>'.nl2br(e($this->body)).'</div>'
            .$unsubscribe
            .'</div>';
    }
}
