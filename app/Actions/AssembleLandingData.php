<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ChemicalReading;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Services\AiQuota;
use App\Services\Chat\AiCredentials;
use App\Services\GeocodingService;
use App\Support\LandingCache;
use App\Support\LandingConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Assemble the server-side `live` data each enabled landing section needs —
 * stats, the showcase gallery, the team, and the service-area map inputs — so
 * the SSR page has everything it renders. The stats come back whether or not
 * their section is on, so the editor can show each live count beside the box
 * for typing your own. The heavy queries are cached briefly per tenant (busted
 * on config save + showcase toggle).
 *
 * Tenant scoping is enforced server-side: the gallery goes THROUGH the
 * tenant-scoped ServiceVisit; the team is filtered by explicit tenant_id (User
 * is not globally scoped) and drops any foreign id.
 */
class AssembleLandingData
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function handle(Tenant $tenant, array $config): array
    {
        // Data only for the sections that are on, so a hidden one (say, the team) isn't in the page's source.
        $enabled = array_column(LandingConfig::enabledOrdered($config), null, 'key');

        $browserKey = config('services.google.browser_maps_key');
        $live = [
            'contactAction' => '/public/'.$tenant->slug.'/leads',
            'chatAction' => '/public/'.$tenant->slug.'/chat',
            'chatEnabled' => $this->chatEnabled($tenant),
            'mapsKey' => is_string($browserKey) && $browserKey !== '' ? $browserKey : null,
        ];

        $cached = Cache::remember(LandingCache::key((int) $tenant->id), now()->addMinutes(10), function () use ($enabled, $tenant): array {
            $out = ['stats' => $this->stats($tenant)];
            if (isset($enabled['gallery'])) {
                $out['gallery'] = $this->gallery((int) $enabled['gallery']['limit']);
            }
            if (isset($enabled['team'])) {
                $out['team'] = $this->team($tenant, (array) $enabled['team']['members']);
            }
            if (isset($enabled['quote']) || isset($enabled['booking'])) {
                $out['services'] = $this->services();
            }

            return $out;
        });

        $live = array_merge($live, $cached);
        if (isset($enabled['service_area'])) {
            $live['serviceArea'] = $this->serviceArea($tenant, $enabled['service_area']);
        }

        return $live;
    }

    /**
     * Where the map centers and the place the card names: the company address,
     * unless the company entered a ZIP (geocoded and cached: 30 days, or 6 hours
     * after a failure so a good ZIP isn't stuck behind a blip). The caption comes
     * from the section's own settings, so the editor's preview follows typing.
     *
     * @param  array<string, mixed>  $section  the sanitized service_area section
     * @return array{lat: mixed, lng: mixed, formattedAddress: string|null}
     */
    private function serviceArea(Tenant $tenant, array $section): array
    {
        $zip = is_string($section['zip']) ? $section['zip'] : null;

        if ($zip !== null) {
            $key = 'geocode_zip:'.$zip;
            $coords = Cache::get($key);
            if ($coords === null) {
                // A country hint is needed — Google returns ZERO_RESULTS for a bare US ZIP.
                $coords = app(GeocodingService::class)->geocode($zip.', USA');
                Cache::put($key, $coords ?? false, $coords !== null ? now()->addDays(30) : now()->addHours(6));
            }
            if (is_array($coords) && isset($coords['lat'], $coords['lng'])) {
                return ['lat' => $coords['lat'], 'lng' => $coords['lng'], 'formattedAddress' => $zip];
            }
        }

        return ['lat' => $tenant->lat, 'lng' => $tenant->lng, 'formattedAddress' => $tenant->formattedAddress()];
    }

    /** @return array<string, int> */
    private function stats(Tenant $tenant): array
    {
        $created = $tenant->getAttribute('created_at');
        $years = $created instanceof Carbon ? (int) abs($created->diffInYears(now())) : 0;

        return [
            'pools_serviced' => Pool::query()->count(),                                  // tenant-scoped
            'visits_completed' => ServiceVisit::query()->where('status', 'completed')->count(), // tenant-scoped
            'years_active' => max(1, $years),
            'happy_customers' => Customer::query()->count(),                             // tenant-scoped
            'water_tests' => ChemicalReading::query()->count(),                          // tenant-scoped
            'gallons_maintained' => (int) Pool::query()->sum('volume_gallons'),          // tenant-scoped
            'technicians' => User::query()->where('tenant_id', $tenant->id)->where('role', 'agent')->where('is_active', true)->count(),
        ];
    }

    /** Whether the public chatbot can actually run: AI key present, enabled, and in-quota for this tenant. */
    private function chatEnabled(Tenant $tenant): bool
    {
        [, $key] = app(AiCredentials::class)->for((int) $tenant->id);
        $quota = app(AiQuota::class);

        return $key !== '' && $quota->enabled((int) $tenant->id) && $quota->remaining((int) $tenant->id) > 0;
    }

    /**
     * The tenant's active service types (for the quote calculator + booking
     * section). Tenant-scoped via ServiceType's global scope.
     *
     * @return list<array<string, mixed>>
     */
    private function services(): array
    {
        // Only what the company offers to customers (active + "customers can request this").
        return ServiceType::query()
            ->where('is_active', true)
            ->where('requestable', true)
            ->orderBy('price')
            ->get(['id', 'name', 'price', 'frequency', 'description'])
            ->map(fn ($s): array => [
                'id' => $s->id,
                'name' => (string) $s->name,
                'price' => (float) $s->price,
                'frequency' => (string) $s->frequency,
                'description' => is_string($s->description) ? $s->description : null,
            ])
            ->all();
    }

    /** @return list<array<string, string|null>> */
    private function gallery(int $limit): array
    {
        return VisitPhoto::query()
            ->where('is_showcase', true)
            ->whereHas('serviceVisit') // forces the tenant-scoped join — only this tenant's visits
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'photo_path', 'caption'])
            ->map(fn (VisitPhoto $p): array => [
                'url' => Storage::disk('public')->url((string) $p->getAttribute('photo_path')),
                'caption' => is_string($cap = $p->getAttribute('caption')) ? $cap : null,
            ])
            ->all();
    }

    /**
     * @param  list<mixed>  $members
     * @return list<array<string, mixed>>
     */
    private function team(Tenant $tenant, array $members): array
    {
        // User isn't tenant-scoped: filter by company, so someone who left (or another company's id) drops out.
        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereIn('id', array_column($members, 'user_id'))
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($members as $m) {
            $user = is_array($m) && is_int($m['user_id']) ? $users->get($m['user_id']) : null;
            if ($user instanceof User) {
                $out[] = ['user_id' => $user->id, 'name' => $user->public_name, 'title' => $m['title'], 'bio' => $m['bio'], 'avatar' => $user->avatar];
            }
        }

        return $out;
    }
}
