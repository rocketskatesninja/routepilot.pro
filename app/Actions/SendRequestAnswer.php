<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\ServiceRequestAnsweredMail;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Notifications\ServiceRequestAnswered;
use Illuminate\Support\Facades\Mail;

/**
 * Tell a customer how the company answered their request — a portal
 * notification (if they have a login) and an email (unless they opted out).
 * Shared by approving and declining so both say it the same way.
 */
class SendRequestAnswer
{
    public function handle(ServiceRequest $request): void
    {
        $customer = $request->customer;
        if ($customer === null) {
            return;
        }

        $company = (string) (Tenant::query()->whereKey($request->getAttribute('tenant_id'))->value('name') ?? '');
        $headline = $this->headline($request);
        $summary = $this->summary($request, $company);

        $user = $customer->user;
        $user?->notify(new ServiceRequestAnswered($headline, $summary));

        $email = $customer->email;
        $emailAllowed = $user === null || $user->wantsNotification('service', 'email');
        if (is_string($email) && $email !== '' && ! $customer->getAttribute('email_opt_out') && $emailAllowed) {
            Mail::to($email)->queue(new ServiceRequestAnsweredMail(
                customerName: $customer->displayName(),
                company: $company,
                headline: $headline,
                summary: $summary,
                reply: $request->reply,
                declined: $request->status === 'declined',
                portalUrl: $user !== null ? url('/requests') : null, // only customers with a login have a portal
            ));
        }
    }

    private function headline(ServiceRequest $request): string
    {
        if ($request->status === 'declined') {
            return 'About your request';
        }

        return match ($request->statusLabel()) {
            'Scheduled' => $request->plan?->frequency === 'one_time' ? 'You\'re booked' : 'You\'re scheduled',
            'Pause set' => 'Your pause is set',
            'Stop confirmed' => 'Your service is stopping',
            default => 'Your request is taken care of',
        };
    }

    /** One plain sentence saying what happened. */
    private function summary(ServiceRequest $request, string $company): string
    {
        $pool = $request->pool?->getAttribute('name') ?? 'your pool';
        $date = fn (?\DateTimeInterface $d): string => $d !== null ? $d->format('l, F j') : '';

        if ($request->status === 'declined') {
            return $company.' can\'t take on your request this time: '.$request->title().'.';
        }

        $plan = $request->plan;
        if ($request->isForService() && $plan !== null) {
            $tech = $plan->agent?->firstName();
            $techLine = $tech !== null ? ' Your technician will be '.$tech.'.' : '';

            return $plan->frequency === 'one_time'
                ? $request->title().' for '.$pool.' is booked for '.$date($plan->starts_on).'.'.$techLine
                : $request->title().' for '.$pool.' is scheduled: '.$plan->scheduleLabel().', starting '.$date($plan->starts_on).'.'.$techLine;
        }

        $yours = 'Your '.($plan->serviceType->name ?? 'service').' for '.$pool;

        return match ($request->type) {
            'hold' => $yours.' is paused from '.$date($request->preferred_date).' through '.$date($request->ends_on).', and starts again automatically after that.',
            'cancel' => $request->preferred_date !== null && $request->preferred_date->gt(Tenant::localToday())
                ? $yours.' will stop after '.$date($request->preferred_date).'.'
                : $yours.' has stopped.',
            default => $company.' has taken care of your request.',
        };
    }
}
