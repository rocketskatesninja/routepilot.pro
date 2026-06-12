<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServiceLocation;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

/**
 * Backfill / retry geocoding for service locations that have an address but no
 * coordinates. Runs across all tenants (the tenant scope self-disables in the
 * console). Scheduled daily so transient Google failures get a fresh attempt.
 */
class GeocodeLocations extends Command
{
    protected $signature = 'app:geocode-locations {--all : Re-geocode every location, not just those missing coordinates}';

    protected $description = 'Geocode service locations missing coordinates (Google Geocoding).';

    public function handle(GeocodingService $geocoder): int
    {
        $query = ServiceLocation::query()->whereNotNull('address_line1');
        if (! $this->option('all')) {
            $query->whereNull('lat');
        }

        $located = 0;
        $failed = 0;
        $query->each(function (ServiceLocation $location) use ($geocoder, &$located, &$failed): void {
            if ($geocoder->locate($location)) {
                $located++;
            } else {
                $failed++;
            }
        });

        $this->info("Geocoded {$located} location(s); {$failed} unresolved.");

        return self::SUCCESS;
    }
}
