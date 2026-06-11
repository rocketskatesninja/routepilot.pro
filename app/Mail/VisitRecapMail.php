<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ServiceVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Per-visit recap emailed to the homeowner after a service visit — chemistry,
 * treatments, current balance + a pay link. Marketing-adjacent, so callers
 * skip it for opt-out customers.
 */
class VisitRecapMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ServiceVisit $visit, public float $balance, public ?string $payUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your pool was serviced — '.$this->visit->pool->customer->tenant->name);
    }

    public function content(): Content
    {
        $visit = $this->visit->load(['pool.customer.tenant', 'chemicalReading', 'treatments']);

        return new Content(view: 'emails.visit-recap', with: [
            'customerName' => $visit->pool->customer->displayName(),
            'company' => $visit->pool->customer->tenant->name,
            'pool' => $visit->pool->name,
            'date' => $visit->completed_at?->toFormattedDateString() ?? '',
            'reading' => $visit->chemicalReading,
            'treatments' => $visit->treatments,
            'balance' => $this->balance,
            'payUrl' => $this->payUrl,
        ]);
    }
}
