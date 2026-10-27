<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ServiceVisit;
use Illuminate\Support\Facades\URL;

/**
 * "Message us" from a visit report email: a signed link (no login) that expires,
 * to a page whose message lands in the company's Requests inbox.
 */
final class ReportMessages
{
    /** Unanswered messages one customer can have waiting, so a forwarded link can't flood the inbox. */
    public const MAX_WAITING = 3;

    /** The report email's link to the page. */
    public static function linkFor(ServiceVisit $visit): string
    {
        return URL::temporarySignedRoute('report-message.show', now()->addDays(30), ['visit' => $visit->id]);
    }
}
