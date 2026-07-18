<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;

/**
 * First-run setup progress for a tenant: the five "getting started" tasks a new
 * tenant_admin should complete (services → landing → agents → customers → pools).
 *
 * Completion is DERIVED from the data itself — a row is "done" once its records
 * exist — so progress is honest and resumable with no tracking column. The only
 * stored state is the tenant's "dismissed" flag (a TenantSetting k/v).
 */
class OnboardingStatus
{
    private const DISMISS_KEY = 'onboarding_dismissed';

    /**
     * @return array{
     *     steps: list<array{key: string, label: string, href: string, done: bool, optional: bool}>,
     *     done: int,
     *     total: int,
     *     complete: bool,
     *     dismissed: bool,
     * }
     */
    public static function for(Tenant $tenant): array
    {
        // ServiceType / Customer / Pool are tenant-scoped (BelongsToTenant), so
        // ::query() already filters to the current tenant. User is NOT globally
        // scoped (cross-tenant hazard) — filter tenant_id + role explicitly.
        //
        // Agents is OPTIONAL: a solo operator who runs the route themselves (the
        // tenant_admin can do agent/field work) has no separate agent, so it must
        // not gate completion — otherwise the panel could never auto-hide.
        $steps = [
            [
                'key' => 'services',
                'label' => 'Create your services',
                'href' => route('services.index'),
                'done' => ServiceType::query()->exists(),
                'optional' => false,
            ],
            [
                'key' => 'landing',
                'label' => 'Set up your landing page',
                'href' => route('company.landing.edit'),
                'done' => TenantSetting::getFor($tenant->id, 'landing') !== null,
                'optional' => false,
            ],
            [
                'key' => 'agents',
                'label' => 'Add your agents',
                'href' => route('people.index', ['type' => 'agent']),
                'done' => User::query()->where('tenant_id', $tenant->id)->where('role', 'agent')->exists(),
                'optional' => true,
            ],
            [
                'key' => 'customers',
                'label' => 'Add customers',
                'href' => route('people.index', ['type' => 'customer']),
                'done' => Customer::query()->exists(),
                'optional' => false,
            ],
            [
                'key' => 'pools',
                'label' => 'Add pools',
                'href' => route('pools.index'),
                'done' => Pool::query()->exists(),
                'optional' => false,
            ],
        ];

        // Progress + completion count the REQUIRED steps only; optional steps still
        // render (and tick green when done) but never block "complete".
        $required = array_filter($steps, static fn (array $s): bool => ! $s['optional']);
        $done = count(array_filter($required, static fn (array $s): bool => $s['done']));

        return [
            'steps' => $steps,
            'done' => $done,
            'total' => count($required),
            'complete' => $done === count($required),
            'dismissed' => TenantSetting::getFor($tenant->id, self::DISMISS_KEY) === '1',
        ];
    }

    /** Hide the checklist for this tenant (the "Dismiss for now" action). */
    public static function dismiss(Tenant $tenant): void
    {
        TenantSetting::setFor($tenant->id, self::DISMISS_KEY, '1');
    }
}
