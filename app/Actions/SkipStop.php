<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\RouteUpdated;
use App\Models\RouteStop;

/**
 * Skip one stop, from the schedule board or the AI assistant. Only a stop that's
 * still due (or was missed) can be skipped; a finished visit stays finished. The
 * live schedule board refreshes.
 */
class SkipStop
{
    /** @return bool false when the stop can't be skipped (nothing changed) */
    public function handle(RouteStop $stop, string $reason): bool
    {
        if (! in_array($stop->status, ['pending', 'missed'], true)) {
            return false;
        }

        $stop->update(['status' => 'skipped', 'skip_reason' => $reason]);
        if ($stop->route !== null) {
            event(RouteUpdated::for($stop->route));
        }

        return true;
    }
}
