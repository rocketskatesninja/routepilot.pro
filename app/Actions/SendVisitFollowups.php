<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\VisitRecapMail;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Notifications\VisitCompleted;
use App\Services\BillingService;
use App\Support\EmailPreferences;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The notifications that fire after a visit is completed — shared by the
 * web (Inertia) and the offline-field (JSON) completion paths so both behave
 * identically: notify the homeowner's portal user and queue the recap email
 * (with an outstanding-balance pay link), honoring opt-out, plus a copy for the
 * pool's second address.
 */
class SendVisitFollowups
{
    public function __construct(private readonly BillingService $billing) {}

    /** @param  bool  $email  false = the office held back the emails for a report it typed in */
    public function handle(ServiceVisit $visit, bool $email = true): void
    {
        $customer = $visit->pool?->customer;

        // Notify the homeowner's portal user, if any (honors their preferences).
        $customer?->user?->notify(new VisitCompleted($visit));

        // Per-visit report email, unless they've turned visit reports off.
        if ($email && $customer !== null && is_string($customer->email) && $customer->email !== '' && EmailPreferences::wants($customer, 'visit_reports')) {
            $balance = $this->billing->outstandingForCustomer($customer);
            $payUrl = $balance > 0 ? URL::signedRoute('pay.link', ['customer' => $customer->id]) : null;
            Mail::to($customer->email)->queue(new VisitRecapMail($visit, $balance, $payUrl));
        }

        // A copy for the pool's second address, if the customer asked for one. It goes even when
        // they've turned their own reports off: it's that person's to stop, with its own link.
        $copyTo = $visit->pool?->report_cc_email;
        if ($email && $customer !== null && is_string($copyTo) && $copyTo !== '' && strcasecmp($copyTo, (string) $customer->email) !== 0) {
            Mail::to($copyTo)->queue(new VisitRecapMail($visit, 0.0, null, copyTo: $copyTo));
        }
    }
}
