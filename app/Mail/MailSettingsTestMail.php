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
use Illuminate\Queue\SerializesModels;

/**
 * The "Send test email" on Company → Email: sent through the company's own
 * mail server to the admin who asked, from the address the company set. If it
 * gets through, the company's emails start going out that way.
 */
class MailSettingsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{address: string, name: string}  $sender  the company's From
     */
    public function __construct(public Tenant $tenant, public array $sender) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->sender['address'], $this->sender['name'] ?: $this->tenant->name),
            subject: 'Your mail settings work — '.$this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mail-settings-test', with: ['brand' => EmailBrand::for($this->tenant)]);
    }
}
