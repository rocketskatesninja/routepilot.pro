<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Requests\UpdateMailConfigRequest;
use App\Models\Integration;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\PhotoService;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
                'logo_url' => $this->photoUrl($tenant->getAttribute('logo_path')),
            ],
            'ai' => [
                'provider' => TenantSetting::getFor($tenant->id, 'ai_provider') ?? 'anthropic',
                'model' => TenantSetting::getFor($tenant->id, 'ai_model') ?? '',
            ],
            'mail' => $this->mailConfig($tenant),
            'connect' => [
                'available' => app(StripeService::class)->configured(),
                'connected' => is_string($tenant->getAttribute('stripe_connect_account_id')) && $tenant->getAttribute('stripe_connect_account_id') !== '',
                'charges_enabled' => (bool) $tenant->getAttribute('stripe_connect_charges_enabled'),
            ],
        ]);
    }

    /** Start (or resume) Stripe Connect onboarding for the tenant. */
    public function connect(Request $request, StripeService $stripe): SymfonyResponse
    {
        $tenant = $this->tenant($request);

        $account = $stripe->createConnectAccount($tenant);
        if ($account === null) {
            return back()->with('error', 'Could not start Stripe Connect — try again shortly.');
        }

        $url = $stripe->createAccountLink($account, url('/company'), url('/company/connect/return'));
        if ($url === null) {
            return back()->with('error', 'Could not create the Stripe onboarding link.');
        }

        return Inertia::location($url);
    }

    /** Stripe redirects here after onboarding — refresh + store the account status. */
    public function connectReturn(Request $request, StripeService $stripe): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $enabled = $stripe->refreshConnectStatus($tenant);

        return redirect('/company')->with(
            'success',
            $enabled
                ? 'Stripe connected — customer payments now go to your account.'
                : 'Stripe onboarding started — finish the remaining steps to enable payouts.',
        );
    }

    public function updateMail(UpdateMailConfigRequest $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validated();

        $existing = Integration::query()->where('integration_type', 'smtp')->first();
        $existingConfig = is_array($existing?->getAttribute('config')) ? $existing->getAttribute('config') : [];
        $password = (string) ($data['password'] ?? '');

        Integration::query()->updateOrCreate(
            ['integration_type' => 'smtp'],
            [
                'provider' => 'smtp',
                'is_active' => true,
                'config' => [
                    'host' => $data['host'],
                    'port' => (int) $data['port'],
                    'encryption' => $data['encryption'] ?? 'tls',
                    'username' => $data['username'] ?? null,
                    // Keep the stored (encrypted) password if the field was left blank.
                    'password' => $password !== '' ? $password : ($existingConfig['password'] ?? null),
                    'from_address' => $data['from_address'] ?? null,
                    'from_name' => $data['from_name'] ?? null,
                ],
            ],
        );

        return back()->with('success', 'Mail settings saved.');
    }

    /**
     * The tenant's SMTP config for the form — never exposes the password.
     *
     * @return array<string, mixed>
     */
    private function mailConfig(Tenant $tenant): array
    {
        $smtp = Integration::query()->where('integration_type', 'smtp')->first();
        $config = is_array($smtp?->getAttribute('config')) ? $smtp->getAttribute('config') : [];

        return [
            'host' => $config['host'] ?? '',
            'port' => $config['port'] ?? 587,
            'encryption' => $config['encryption'] ?? 'tls',
            'username' => $config['username'] ?? '',
            'from_address' => $config['from_address'] ?? '',
            'from_name' => $config['from_name'] ?? '',
            'has_password' => ! empty($config['password']),
            'active' => (bool) ($smtp?->getAttribute('is_active')) && ! empty($config['host']),
        ];
    }

    public function update(UpdateCompanyRequest $request, PhotoService $photos): RedirectResponse
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

        $logo = $data['logo'] ?? null;
        if ($logo instanceof UploadedFile) {
            $old = $tenant->getAttribute('logo_path');
            $tenant->forceFill(['logo_path' => $photos->replace($logo, is_string($old) ? $old : null, 'tenants')])->save();
        }

        TenantSetting::setFor($tenant->id, 'ai_provider', (string) $data['ai_provider']);
        TenantSetting::setFor($tenant->id, 'ai_model', (string) ($data['ai_model'] ?? ''));

        return back()->with('success', 'Company settings saved.');
    }

    /** Public URL for a stored photo path, or null when unset. */
    private function photoUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
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
