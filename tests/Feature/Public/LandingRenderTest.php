<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use Inertia\Testing\AssertableInertia as Assert;

test('a tenant custom-domain host renders its landing page', function () {
    Tenant::factory()->create(['primary_domain' => 'acme-pools.test', 'name' => 'Acme Pools']);

    $this->get('http://acme-pools.test/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Landing')
            ->has('sections')
            ->where('seo.title', 'Acme Pools')
            ->has('seo.description')
        );
});

test('a tenant is reachable by path on the platform host (/t/{slug})', function () {
    Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Pools']);

    $this->get('/t/acme')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Landing')
            ->has('sections')
            ->where('seo.title', 'Acme Pools')
        );
});

test('the bare platform host shows RoutePilot marketing, not a tenant landing', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('a {slug}.routepilot.pro subdomain no longer resolves a tenant', function () {
    Tenant::factory()->create(['slug' => 'acme', 'primary_domain' => null]);

    // The subdomain fallback was removed; no custom domain matches this host,
    // so a subdomain serves the platform marketing page — NOT the tenant landing.
    $this->get('http://acme.routepilot.pro/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('a suspended tenant site is not public', function () {
    Tenant::factory()->create(['slug' => 'acme', 'status' => 'suspended']);

    $this->get('/t/acme')->assertNotFound();
});

test('disabled and unknown sections never reach the public render', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    // Bind the tenant so BelongsToTenant fills tenant_id on the stored setting.
    app()->instance('tenant_id', $tenant->id);
    TenantSetting::setFor($tenant->id, 'landing', (string) json_encode([
        'sections' => [
            ['key' => 'hero', 'enabled' => true, 'headline' => 'Hi', 'evil' => '<script>'],
            ['key' => 'faq', 'enabled' => false],
            ['key' => '__evil', 'enabled' => true, 'heading' => 'pwn'],
        ],
    ]));
    app()->forgetInstance('tenant_id');

    $this->get('/t/acme')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Landing')
            ->where('sections', function ($sections) {
                $keys = collect($sections)->pluck('key');
                $hero = collect($sections)->firstWhere('key', 'hero');

                return $keys->doesntContain('faq')        // disabled excluded
                    && $keys->doesntContain('__evil')      // unknown key excluded
                    && $hero !== null
                    && ! array_key_exists('evil', $hero);  // unknown field stripped
            })
        );
});
