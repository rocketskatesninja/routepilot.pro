<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\VisitRecapMail;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Notifications\VisitCompleted;
use App\Services\BillingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * The notifications that fire after a visit is completed — shared by the
 * web (Inertia) and the offline-field (JSON) completion paths so both behave
 * identically: notify the homeowner's portal user and queue the recap email
 * (with an outstanding-balance pay link), honoring opt-out.
 */
class SendVisitFollowups
{
    public function __construct(private readonly BillingService $billing) {}

    public function handle(ServiceVisit $visit): void
    {
        $customer = $visit->pool?->customer;

        // Notify the homeowner's portal user, if any (honors their preferences).
        $customerUserId = $customer?->getAttribute('user_id');
        if ($customerUserId !== null) {
            $customerUser = User::find($customerUserId);
            if ($customerUser !== null) {
                Notification::send($customerUser, new VisitCompleted($visit));
            }
        }

        // Per-visit recap email (skipped for opt-out customers).
        if ($customer !== null && is_string($customer->email) && $customer->email !== '' && ! $customer->email_opt_out) {
            $balance = $this->billing->outstandingForCustomer($customer);
            $payUrl = $balance > 0 ? URL::signedRoute('pay.link', ['customer' => $customer->id]) : null;
            Mail::to($customer->email)->queue(new VisitRecapMail($visit, $balance, $payUrl));
        }
    }
}
