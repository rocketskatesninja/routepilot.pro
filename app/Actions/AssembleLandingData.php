<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Pool;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Services\AiQuota;
use App\Services\Chat\AiCredentials;
use App\Support\LandingCache;
use App\Support\LandingConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Assemble the server-side `live` data each enabled landing section needs —
 * stats, the showcase gallery, the team, and the service-area map inputs — so
 * the SSR page has everything it renders. The heavy queries are cached briefly
 * per tenant (busted on config save + showcase toggle).
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
        $enabled = [];
        foreach (LandingConfig::enabledOrdered($config) as $section) {
            $key = $section['key'] ?? null;
            if (is_string($key)) {
                $enabled[$key] = $section;
            }
        }

        $browserKey = config('services.google.browser_maps_key');
        $live = [
            'contactAction' => '/public/'.$tenant->slug.'/leads',
            'chatAction' => '/public/'.$tenant->slug.'/chat',
            'chatEnabled' => $this->chatEnabled($tenant),
            'mapsKey' => is_string($browserKey) && $browserKey !== '' ? $browserKey : null,
        ];

        $cached = Cache::remember(LandingCache::key((int) $tenant->id), now()->addMinutes(10), function () use ($enabled, $tenant): array {
            $out = [];
            if (isset($enabled['stats'])) {
                $out['stats'] = $this->stats($tenant);
            }
            if (isset($enabled['gallery'])) {
                $out['gallery'] = $this->gallery((int) ($enabled['gallery']['limit'] ?? 12));
            }
            if (isset($enabled['team'])) {
                $members = is_array($enabled['team']['members'] ?? null) ? $enabled['team']['members'] : [];
                $out['team'] = $this->team($tenant, $members);
            }
            if (isset($enabled['quote']) || isset($enabled['booking'])) {
                $out['services'] = $this->services();
            }

            return $out;
        });

        $live = array_merge($live, $cached);

        // Service-area reads live tenant coords + is cheap — not cached.
        if (isset($enabled['service_area'])) {
            $radius = $enabled['service_area']['radius_label'] ?? null;
            $live['serviceArea'] = [
                'lat' => $tenant->lat,
                'lng' => $tenant->lng,
                'formattedAddress' => $tenant->formattedAddress(),
                'radiusLabel' => is_string($radius) ? $radius : null,
            ];
        }

        return $live;
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
        return ServiceType::query()
            ->where('is_active', true)
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
            ->limit(max(1, min(24, $limit)))
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
        $ids = [];
        foreach ($members as $m) {
            if (is_array($m) && is_int($m['user_id'] ?? null)) {
                $ids[] = $m['user_id'];
            }
        }
        if ($ids === []) {
            return [];
        }

        // User is NOT globally tenant-scoped → filter explicitly + drop foreign ids.
        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($members as $m) {
            if (! is_array($m)) {
                continue;
            }
            $uid = $m['user_id'] ?? null;
            $user = is_int($uid) ? $users->get($uid) : null;
            if (! $user instanceof User) {
                continue;
            }
            $out[] = [
                'user_id' => $user->id,
                'name' => $user->public_name,
                'title' => is_string($m['title'] ?? null) ? $m['title'] : null,
                'bio' => is_string($m['bio'] ?? null) ? $m['bio'] : null,
                'avatar' => $user->avatar,
            ];
        }

        return $out;
    }
}
