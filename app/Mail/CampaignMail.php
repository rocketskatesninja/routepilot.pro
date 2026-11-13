<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use App\Support\EmailBrand;
use App\Support\EmailHtml;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
        /** Fills {first name} in the subject and message. */
        public ?string $firstName = null,
        /** @var list<array{name: string, size: int, mime: string, path: string}> Files sent with it (private disk). */
        public array $files = [],
    ) {}

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $out = [];
        foreach ($this->files as $file) {
            if (Storage::disk('local')->exists($file['path'])) {
                $out[] = Attachment::fromStorageDisk('local', $file['path'])->as($file['name'])->withMime($file['mime']);
            }
        }

        return $out;
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->oneClickUrl !== null
            ? ['List-Unsubscribe' => '<'.$this->oneClickUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']
            : []);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: str_ireplace(EmailHtml::FIRST_NAME, trim((string) $this->firstName) !== '' ? trim((string) $this->firstName) : 'there', $this->subjectLine),
            from: $this->fromAddress !== null ? new Address($this->fromAddress['address'], $this->fromAddress['name']) : null,
            replyTo: EmailBrand::replyTo($this->tenant),
        );
    }

    public function content(): Content
    {
        // Cleaned again here as well as on the way in (and a plain-text message from
        // before rich text keeps its line breaks), then the first name goes in.
        $html = EmailHtml::personalise(EmailHtml::clean(EmailHtml::fromPlain($this->body)), $this->firstName);

        return new Content(
            view: 'emails.campaign',
            text: 'emails.campaign-text',
            with: ['brand' => EmailBrand::for($this->tenant), 'html' => EmailHtml::styled($html), 'text' => EmailHtml::toText($html)],
        );
    }
}
