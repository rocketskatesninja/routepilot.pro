<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\PlatformAiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin AI console. AI is a platform-managed, bundled feature: the
 * super-admin owns the provider, model, and API keys, and meters each tenant
 * with a monthly message quota. Keys are encrypted at rest and never returned
 * to the UI in full (see PlatformAiSettings::keyStatus()).
 */
class PlatformAiController extends Controller
{
    public function edit(Request $request, PlatformAiSettings $platform): Response
    {
        $this->authorizeSuper($request);

        // Platform-level config only; per-tenant AI lives on the Tenants console
        // (each tenant's detail pane), saved via updateTenant() below.
        return Inertia::render('admin/Ai', [
            'defaults' => [
                'provider' => $platform->provider(),
                'model' => $platform->model(),
                'default_quota' => $platform->defaultQuota(),
            ],
            'keys' => $platform->keyStatus(),
            'modelHints' => config('ai.models'),
        ]);
    }

    public function update(Request $request, PlatformAiSettings $platform): RedirectResponse
    {
        $this->authorizeSuper($request);

        $data = $request->validate([
            'provider' => ['required', 'in:anthropic,openai'],
            'model' => ['nullable', 'string', 'max:100'],
            'default_quota' => ['required', 'integer', 'min:0', 'max:1000000'],
            'anthropic_key' => ['nullable', 'string', 'max:500'],
            'openai_key' => ['nullable', 'string', 'max:500'],
        ]);

        PlatformSetting::set('ai_provider', $data['provider']);
        PlatformSetting::set('ai_model', (string) ($data['model'] ?? ''));
        PlatformSetting::set('ai_default_quota', (string) $data['default_quota']);

        // Keys are write-only: a non-empty field rotates that provider's key;
        // an empty field leaves the existing key untouched.
        foreach (['anthropic', 'openai'] as $provider) {
            $field = "{$provider}_key";
            $incoming = $data[$field] ?? null;
            if (is_string($incoming) && $incoming !== '') {
                $platform->setKey($provider, $incoming);
                AuditLog::record($request->user(), 'platform.ai.key_rotated', null, ['provider' => $provider]);
            }
        }

        return back()->with('success', 'AI settings saved.');
    }

    public function updateTenant(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeSuper($request);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'allow_override' => ['required', 'boolean'],
            'quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        TenantSetting::setFor($tenant->id, 'ai_enabled', $data['enabled'] ? '1' : '0');
        TenantSetting::setFor($tenant->id, 'ai_allow_override', $data['allow_override'] ? '1' : '0');

        if (array_key_exists('quota', $data) && $data['quota'] !== null) {
            TenantSetting::setFor($tenant->id, 'ai_monthly_quota', (string) $data['quota']);
        } else {
            // No override → fall back to the platform default quota.
            TenantSetting::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->where('key', 'ai_monthly_quota')->delete();
        }

        AuditLog::record($request->user(), 'platform.ai.tenant_updated', $tenant, [
            'enabled' => $data['enabled'], 'allow_override' => $data['allow_override'], 'quota' => $data['quota'] ?? null,
        ]);

        return back()->with('success', "AI settings updated for {$tenant->name}.");
    }
}
