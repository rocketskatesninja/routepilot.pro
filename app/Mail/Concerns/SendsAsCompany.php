<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\Tenant;
use App\Services\TenantMailer;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * A company's email to its customers goes out through the company's own mail
 * server when it has one switched on. If that server can't send it, the email
 * goes out from RoutePilot instead (so the customer still gets it) and the
 * company is told. Laravel calls send() at the moment of sending — in the queue
 * worker for queued mail — so the company's current settings are always used.
 */
trait SendsAsCompany
{
    /** Set on the copy that goes out from RoutePilot after the company's server failed. */
    protected bool $viaPlatform = false;

    /** The company the email is from; null = RoutePilot's own. */
    abstract protected function company(): ?Tenant;

    /**
     * @param  MailFactory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        $company = $this->viaPlatform ? null : $this->company();
        $route = $company !== null ? app(TenantMailer::class)->prepare($company->id) : null;
        if ($company === null || $route === null || $route['from'] === null) {
            return parent::send($mailer);
        }

        // Sending prepares the email in place (headers, envelope), so a second try starts from a copy.
        $fallback = clone $this;
        try {
            $this->from($route['from']['address'], $route['from']['name'] ?: $company->name);
            $sent = parent::send(Mail::mailer($route['mailer']));
            app(TenantMailer::class)->recordSuccess($company);

            return $sent;
        } catch (TransportExceptionInterface $e) {
            app(TenantMailer::class)->recordFailure($company, $e);
            $fallback->viaPlatform = true;
            // Replies should still reach the company, not RoutePilot.
            if (! is_string($company->reply_to_email) || $company->reply_to_email === '') {
                $fallback->replyTo($route['from']['address'], $route['from']['name'] ?: $company->name);
            }

            return $fallback->send($mailer);
        }
    }
}
