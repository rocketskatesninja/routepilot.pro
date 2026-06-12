<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use Inertia\Testing\AssertableInertia as Assert;

test('a resolved tenant host renders its landing page', function () {
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

test('the bare platform host shows RoutePilot marketing, not a tenant landing', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('disabled and unknown sections never reach the public render', function () {
    $tenant = Tenant::factory()->create(['primary_domain' => 'acme-pools.test']);

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

    $this->get('http://acme-pools.test/')
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
