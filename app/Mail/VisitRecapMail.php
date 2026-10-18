<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ServiceVisit;
use App\Support\EmailBrand;
use App\Support\EmailPreferences;
use App\Support\WaterReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Per-visit report emailed to the homeowner after a service visit — what was
 * done, the water test against their pool's own targets, chemicals, the tech's
 * note and photos, plus their balance and a pay link. Marketing-adjacent, so
 * callers skip it for opt-out customers.
 */
class VisitRecapMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ServiceVisit $visit, public float $balance, public ?string $payUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your pool was serviced — '.$this->visit->pool->customer->tenant->name);
    }

    public function headers(): Headers
    {
        return EmailPreferences::headers((int) $this->visit->pool->getAttribute('customer_id'), 'visit_reports');
    }

    public function content(): Content
    {
        $visit = $this->visit->load([
            'pool.customer.tenant', 'chemicalReading', 'treatments', 'tasks', 'photos',
            'agent:id,first_name,last_name', 'serviceSubscription.serviceType:id,name',
        ]);
        $customer = $visit->pool->customer;
        $tenant = $customer->tenant;
        $checks = WaterReport::checks($visit->chemicalReading, $visit->pool);
        // The company's own calendar day — a queued email has no request to tell it.
        $timezone = (string) ($tenant->getAttribute('timezone') ?: config('app.timezone'));

        return new Content(view: 'emails.visit-recap', with: [
            'brand' => EmailBrand::for($tenant),
            'firstName' => $customer->first_name ?: $customer->displayName(),
            'pool' => $visit->pool->name,
            'service' => $visit->serviceSubscription?->serviceType?->name,
            'agent' => $visit->agent?->firstName(),
            'date' => $visit->completed_at?->copy()->setTimezone($timezone)->format('l, F j') ?? '',
            'tasks' => $visit->tasks,
            'checks' => $checks,
            'waterSummary' => WaterReport::summary($checks),
            'treatments' => $visit->treatments,
            'notes' => $visit->notes,
            // Absolute URLs — email clients can't resolve relative paths.
            'photos' => $visit->photos
                ->map(fn ($p) => asset('storage/'.$p->getAttribute('photo_path')))
                ->all(),
            'balance' => $this->balance,
            'payUrl' => $this->payUrl,
            // Only customers with a portal login can open the full report there.
            'reportUrl' => $customer->getAttribute('user_id') !== null ? url('/history?selected='.$visit->id) : null,
            'stopUrl' => EmailPreferences::pageUrl($customer->id, 'visit_reports'),
        ]);
    }
}
