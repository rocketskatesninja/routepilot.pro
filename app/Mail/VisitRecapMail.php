<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\SendsAsCompany;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Support\EmailBrand;
use App\Support\EmailPreferences;
use App\Support\ReportCopies;
use App\Support\ReportMessages;
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
 *
 * With $copyTo it's the copy for the pool's second address (a landlord, a
 * property manager): the same report without the customer's balance, pay link
 * or portal, and with its own "stop these copies" link.
 */
class VisitRecapMail extends Mailable
{
    use Queueable, SendsAsCompany, SerializesModels;

    public function __construct(public ServiceVisit $visit, public float $balance, public ?string $payUrl, public ?string $copyTo = null)
    {
        // A copy never shows the customer's balance or pay link. (Public properties reach the view as-is.)
        if ($copyTo !== null) {
            $this->balance = 0.0;
            $this->payUrl = null;
        }
    }

    protected function company(): ?Tenant
    {
        return $this->visit->pool->customer->tenant;
    }

    public function envelope(): Envelope
    {
        $customer = $this->visit->pool->customer;
        $whose = $this->copyTo !== null ? ($customer->first_name ?: $customer->displayName()).'’s pool' : 'Your pool';

        return new Envelope(subject: $whose.' was serviced — '.$customer->tenant->name, replyTo: EmailBrand::replyTo($customer->tenant));
    }

    public function headers(): Headers
    {
        return $this->copyTo !== null
            ? ReportCopies::headers($this->visit->pool, $this->copyTo)
            : EmailPreferences::headers((int) $this->visit->pool->getAttribute('customer_id'), 'visit_reports');
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
            'copy' => $this->copyTo !== null,
            'balance' => $this->balance,
            'payUrl' => $this->payUrl,
            // Only customers with a portal login can open the full report there (never from a copy).
            'reportUrl' => $this->copyTo === null && $customer->getAttribute('user_id') !== null ? url('/history?selected='.$visit->id) : null,
            // "Message us", when the company takes messages (never on a copy: it isn't that person's visit).
            'messageUrl' => $this->copyTo === null && $tenant->takesReportMessages() ? ReportMessages::linkFor($visit) : null,
            'stopUrl' => $this->copyTo !== null
                ? ReportCopies::pageUrl($visit->pool, $this->copyTo)
                : EmailPreferences::pageUrl($customer->id, 'visit_reports'),
        ]);
    }
}
