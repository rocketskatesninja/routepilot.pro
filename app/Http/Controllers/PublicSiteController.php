<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\LandingConfig;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing surface. On a resolved tenant host (custom domain or
 * {slug}.routepilot.pro — bound by ResolveTenant) the root renders that
 * tenant's section-based landing page (SSR); on the bare platform host it
 * falls back to RoutePilot's own marketing page.
 *
 * P1: renders the (sanitized) config + SEO. Live-data sections (gallery/stats/
 * team/map) are assembled in P3 via AssembleLandingData.
 */
class PublicSiteController extends Controller
{
    public function show(): Response
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;
        if (! $tenant instanceof Tenant) {
            return Inertia::render('Welcome');
        }

        $config = LandingConfig::fromStored(TenantSetting::getFor($tenant->id, 'landing'));
        $sections = array_map(self::withImageUrls(...), LandingConfig::enabledOrdered($config));

        return Inertia::render('public/Landing', [
            'sections' => $sections,
            'seo' => $this->seo($config, $tenant),
            // Brand (name / logo / color) arrives via the shared `tenant` prop.
        ]);
    }

    /**
     * Map a section's stored `image_path` to a public `image_url` for rendering.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private static function withImageUrls(array $section): array
    {
        if (array_key_exists('image_path', $section)) {
            $path = $section['image_path'];
            $section['image_url'] = is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
        }

        return $section;
    }

    /**
     * SEO title/description/OG image with sensible tenant fallbacks.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string|null>
     */
    private function seo(array $config, Tenant $tenant): array
    {
        $seo = is_array($config['seo'] ?? null) ? $config['seo'] : [];
        $title = is_string($seo['title'] ?? null) && $seo['title'] !== '' ? $seo['title'] : $tenant->name;
        $description = is_string($seo['description'] ?? null) && $seo['description'] !== ''
            ? $seo['description']
            : 'Professional, reliable pool service from '.$tenant->name.'.';

        $ogPath = is_string($seo['og_image'] ?? null) && $seo['og_image'] !== ''
            ? $seo['og_image']
            : $tenant->logo_path;
        $ogImage = is_string($ogPath) && $ogPath !== '' ? Storage::disk('public')->url($ogPath) : null;

        return ['title' => $title, 'description' => $description, 'og_image' => $ogImage];
    }
}
