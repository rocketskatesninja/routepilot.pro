<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WeatherService;
use Illuminate\Console\Command;

/**
 * Pre-fetch the forecast for every active tenant's business location so the
 * dashboard weather widget is always served from a warm cache and visitor page
 * loads never call the (rate-limited) weather API inline. Locations are deduped
 * by their cache-key precision (coords rounded to 2 decimals), so tenants that
 * share a town are fetched once. Scheduled every 30 minutes; the cache TTL
 * (45 min) outlives that interval so it never goes cold between runs.
 */
class WarmWeather extends Command
{
    protected $signature = 'app:warm-weather';

    protected $description = 'Refresh cached weather forecasts for active tenants (keeps the dashboard widget warm).';

    public function handle(WeatherService $weather): int
    {
        // Unique rounded coordinates across active, geocoded tenants — the tenant
        // scope self-disables in the console, so this legitimately spans tenants.
        $coords = Tenant::query()
            ->where('status', 'active')
            ->whereNotNull('lat')->whereNotNull('lng')
            ->get(['lat', 'lng'])
            ->map(fn (Tenant $t): array => [round((float) $t->lat, 2), round((float) $t->lng, 2)])
            ->unique(fn (array $c): string => $c[0].','.$c[1])
            ->values();

        $warmed = 0;
        $failed = 0;
        foreach ($coords as [$lat, $lng]) {
            if ($weather->warm($lat, $lng) !== null) {
                $warmed++;
            } else {
                $failed++;
            }
        }

        $this->info("Warmed {$warmed} location(s); {$failed} unavailable.");

        return self::SUCCESS;
    }
}
