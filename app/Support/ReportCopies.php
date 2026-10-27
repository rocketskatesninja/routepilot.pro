<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pool;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * Copies of a pool's visit reports for a second address the customer asked for
 * (a landlord, a property manager). That person isn't the customer, so their
 * copy carries its own signed "stop these copies" link, which can only remove
 * the address it was sent to — never the customer's own reports.
 */
final class ReportCopies
{
    /** The page that asks before stopping copies to this address. */
    public static function pageUrl(Pool $pool, string $email): string
    {
        return URL::signedRoute('report-copies.show', ['pool' => $pool->id, 'email' => $email]);
    }

    /** Where that page's button posts. */
    public static function stopUrl(Pool $pool, string $email): string
    {
        return URL::signedRoute('report-copies.stop', ['pool' => $pool->id, 'email' => $email]);
    }

    /** The one-click unsubscribe headers (RFC 8058) for a copy. */
    public static function headers(Pool $pool, string $email): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.URL::signedRoute('report-copies.one-click', ['pool' => $pool->id, 'email' => $email]).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /** Whether the pool's copies still go to this address. */
    public static function goesTo(Pool $pool, string $email): bool
    {
        return is_string($pool->report_cc_email) && strcasecmp($pool->report_cc_email, $email) === 0;
    }

    /** Stop the copies — only if they still go to this address, so an old link can't remove a newer one. */
    public static function stop(Pool $pool, string $email): void
    {
        if (self::goesTo($pool, $email)) {
            $pool->update(['report_cc_email' => null]);
        }
    }
}
