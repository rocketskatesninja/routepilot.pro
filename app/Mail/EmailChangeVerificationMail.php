<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the NEW address a customer wants to switch to. The account's login
 * (and contact) email only changes once they click the signed confirmation
 * link, proving they control the new inbox.
 */
class EmailChangeVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $newEmail,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your new email address');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.email-change-verification', with: [
            'name' => $this->name,
            'newEmail' => $this->newEmail,
            'confirmUrl' => $this->confirmUrl,
        ]);
    }
}
