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
 * Branded "service tomorrow" reminder emailed to a homeowner. Sent by
 * DailyOpsChecks; callers skip opt-out customers.
 */
class ServiceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $company,
        public string $poolName,
        public string $date,
        public ?string $agentName,
        public ?string $arrivalWindow = null,
        /** The company whose colours and logo the email wears (null = RoutePilot's own). */
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pool service tomorrow — '.$this->poolName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.service-reminder', with: [
            'brand' => EmailBrand::for($this->tenant),
            'customerName' => $this->customerName,
            'company' => $this->company,
            'pool' => $this->poolName,
            'date' => $this->date,
            'agentName' => $this->agentName,
            'arrivalWindow' => $this->arrivalWindow,
        ]);
    }
}
