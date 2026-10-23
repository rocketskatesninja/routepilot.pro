<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssembleLandingData;
use App\Models\Tenant;
use App\Support\LandingConfig;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing surface. A company's site is reached either by its own
 * custom domain (resolved by host in ResolveTenant) or by a path on the platform
 * host — routepilot.pro/t/{slug}, not a subdomain. The bare platform host shows
 * RoutePilot's own marketing page.
 */
class PublicSiteController extends Controller
{
    /** Root: a custom-domain host → that tenant's landing; bare host → RoutePilot marketing. */
    public function show(AssembleLandingData $assemble): Response
    {
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return $tenant instanceof Tenant ? $this->render($tenant, $assemble) : $this->marketing();
    }

    /**
     * RoutePilot's own marketing homepage (bare platform host). Pricing comes from
     * config/billing.php so the page never drifts from real billing; the FAQ is
     * shared with the FAQPage JSON-LD (below) so the copy can't diverge.
     */
    private function marketing(): Response
    {
        $pricing = [
            'base_price' => round((float) config('billing.base_price'), 2),
            'included_pools' => (int) config('billing.included_pools'),
            'included_agents' => (int) config('billing.included_agents'),
            'price_per_pool' => (float) config('billing.price_per_pool'),
            'price_per_agent' => (float) config('billing.price_per_agent'),
        ];

        $faq = [
            [
                'q' => "What's included in the base price?",
                'a' => "Everything — route optimization, AI chemistry, scheduling, the customer website + portal, billing, service reports, and the field app. The base covers {$pricing['included_pools']} pools and {$pricing['included_agents']} agents.",
            ],
            [
                'q' => 'Are there any hidden fees?',
                'a' => 'No. Beyond the included allowance you pay a simple, published rate: $'.number_format($pricing['price_per_pool'], 2).' per extra pool and $'.number_format($pricing['price_per_agent'], 0).' per extra agent, per month. No setup fees and no per-feature upsells.',
            ],
            [
                'q' => 'Do I need a credit card to start?',
                'a' => 'No. The 14-day free trial needs no credit card, and you can cancel anytime.',
            ],
            [
                'q' => 'What size company is this for?',
                'a' => 'From a solo operator to a multi-truck company. The base plan fits most small-to-mid routes; larger operations simply add pools and agents as they grow.',
            ],
        ];

        $base = rtrim((string) config('app.url'), '/');
        $ogImage = $base.'/assets/images/screenshots/demo-landing.jpg';

        $jsonLd = (string) json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'RoutePilot',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'description' => 'All-in-one pool service management software: route optimization, AI chemistry, a branded customer website + portal, automated billing, and a field app for technicians.',
                    'url' => $base.'/',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => number_format($pricing['base_price'], 2, '.', ''),
                        'priceCurrency' => 'USD',
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(fn (array $f): array => [
                        '@type' => 'Question',
                        'name' => $f['q'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                    ], $faq),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        return Inertia::render('Welcome', [
            'pricing' => $pricing,
            'faq' => $faq,
            'canonical' => $base.'/',
            'ogImage' => $ogImage,
            'jsonLd' => $jsonLd,
        ]);
    }

    /** Path-based tenant site on the platform host: routepilot.pro/t/{slug}. */
    public function showBySlug(Tenant $tenant, AssembleLandingData $assemble): Response
    {
        return $this->render($tenant, $assemble);
    }

    private function render(Tenant $tenant, AssembleLandingData $assemble): Response
    {
        abort_unless($tenant->getAttribute('status') === 'active', 404);

        // Bind the tenant so the shared `tenant` prop, brand injection, and any
        // tenant-scoped section data resolve correctly (the path route reaches
        // here without ResolveTenant having bound a tenant by host).
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenant->id);

        $config = LandingConfig::forTenant($tenant->id);

        return Inertia::render('public/Landing', [
            'sections' => LandingConfig::withImageUrls(LandingConfig::enabledOrdered($config)),
            'seo' => $this->seo($config['seo'], $tenant),
            'live' => $assemble->handle($tenant, $config),
            'chatbot' => $config['theme']['chatbot'],
            'title' => $config['title'],
            // Footer social links: only the platforms the company set.
            'social' => array_filter($config['social']),
            // Brand (name / logo / color) arrives via the shared `tenant` prop.
        ]);
    }

    /**
     * The page's title, description and share image, falling back to the company
     * name and logo. (The config's text is already trimmed, with blank as null.)
     *
     * @param  array{title: string|null, description: string|null, og_image: string|null}  $seo
     * @return array<string, string|null>
     */
    private function seo(array $seo, Tenant $tenant): array
    {
        $image = $seo['og_image'] ?? $tenant->logo_path;

        return [
            'title' => $seo['title'] ?? $tenant->name,
            'description' => $seo['description'] ?? 'Professional, reliable pool service from '.$tenant->name.'.',
            'og_image' => is_string($image) && $image !== '' ? Storage::disk('public')->url($image) : null,
        ];
    }
}
