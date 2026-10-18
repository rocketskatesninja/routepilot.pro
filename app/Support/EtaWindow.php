<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Soften a precise ETA into a customer-friendly one-hour arrival window
 * (e.g. "2:00 – 3:00 PM"), so the homeowner never sees false-precision minutes.
 */
class EtaWindow
{
    public static function for(?Carbon $eta): ?string
    {
        if ($eta === null) {
            return null;
        }

        // ETAs are stored as real (UTC) moments; customers read them on the company's clock.
        $start = Tenant::local($eta)->minute(0)->second(0);
        $end = $start->copy()->addHour();

        // Drop the meridiem from the start only when both sides share it.
        return $start->format('A') === $end->format('A')
            ? $start->format('g:i').' – '.$end->format('g:i A')
            : $start->format('g:i A').' – '.$end->format('g:i A');
    }
}
