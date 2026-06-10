<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company settings (tenant_admin) — brand/timezone/tax on the Tenant, AI
 * provider + model in tenant_settings. Privilege fields are never edited here.
 */
class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $tenant = $this->tenant($request);

        return Inertia::render('settings/Company', [
            'company' => [
                'name' => $tenant->name,
                'timezone' => $tenant->getAttribute('timezone'),
                'brand_color' => $tenant->getAttribute('brand_color'),
                'tax_rate_percent' => round((float) $tenant->getAttribute('tax_rate') * 100, 2),
            ],
            'ai' => [
                'provider' => TenantSetting::getFor($tenant->id, 'ai_provider') ?? 'anthropic',
                'model' => TenantSetting::getFor($tenant->id, 'ai_model') ?? '',
            ],
        ]);
    }

    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validated();

        $tenant->fill([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'brand_color' => $data['brand_color'],
        ]);
        // tax_rate is not mass-assignable (billing field) — set deliberately.
        $tenant->forceFill(['tax_rate' => round(((float) $data['tax_rate_percent']) / 100, 4)]);
        $tenant->save();

        TenantSetting::setFor($tenant->id, 'ai_provider', (string) $data['ai_provider']);
        TenantSetting::setFor($tenant->id, 'ai_model', (string) ($data['ai_model'] ?? ''));

        return back()->with('success', 'Company settings saved.');
    }

    /** The admin's own tenant. */
    private function tenant(Request $request): Tenant
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'tenant_admin', 403);
        $tenant = $user->tenant;
        abort_if($tenant === null, 403);

        return $tenant;
    }
}
