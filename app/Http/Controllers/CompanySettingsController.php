<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Requests\UpdateMailConfigRequest;
use App\Mail\MailSettingsTestMail;
use App\Models\AuditLog;
use App\Models\Integration;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\GeocodingService;
use App\Services\PhotoService;
use App\Services\StripeService;
use App\Services\TenantMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
                'address_line1' => $tenant->getAttribute('address_line1'),
                'address_line2' => $tenant->getAttribute('address_line2'),
                'city' => $tenant->getAttribute('city'),
                'state' => $tenant->getAttribute('state'),
                'postal_code' => $tenant->getAttribute('postal_code'),
                'reply_to_email' => $tenant->reply_to_email,
                'report_messages' => $tenant->takesReportMessages(),
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

        $this->audit($request, $tenant, 'company.stripe.connect_started');

        return Inertia::location($url);
    }

    /** Stripe redirects here after onboarding — refresh + store the account status. */
    public function connectReturn(Request $request, StripeService $stripe): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $enabled = $stripe->refreshConnectStatus($tenant);

        $this->audit($request, $tenant, 'company.stripe.onboarding_returned', ['charges_enabled' => $enabled]);

        return redirect('/company')->with(
            'success',
            $enabled
                ? 'Stripe connected — customer payments now go to your account.'
                : 'Stripe onboarding started — finish the remaining steps to enable payouts.',
        );
    }

    public function updateMail(UpdateMailConfigRequest $request, TenantMailer $mailer): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validated();

        $existing = Integration::query()->where('integration_type', 'smtp')->first();
        $existingConfig = is_array($existing?->getAttribute('config')) ? $existing->getAttribute('config') : [];
        $password = (string) ($data['password'] ?? '');

        // Saved switched off: a test email has to get through before the company's mail goes this way.
        Integration::query()->updateOrCreate(
            ['integration_type' => 'smtp'],
            [
                'provider' => 'smtp',
                'is_active' => false,
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

        $mailer->recordSuccess($tenant); // a stale failure belonged to the old settings
        $this->audit($request, $tenant, 'company.mail.updated', ['host' => $data['host']]);

        return back()->with('success', 'Saved. Send a test email to turn it on.');
    }

    /**
     * Send a test through the company's saved mail server to the admin asking. If it
     * gets through, the company's customer emails go out that way from now on. No
     * fallback here: the point is to find out whether their server works.
     */
    public function testMail(Request $request, TenantMailer $mailer): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $user = $request->user();
        abort_if($user === null, 403);

        if (! Integration::query()->where('integration_type', 'smtp')->exists()) {
            return back()->with('error', 'Save your mail server settings first.');
        }
        $route = $mailer->prepare($tenant->id, evenIfOff: true);
        if ($route['from'] === null) {
            return back()->with('error', 'That mail server address can’t be used.');
        }

        try {
            Mail::mailer($route['mailer'])->to($user->email)->send(new MailSettingsTestMail($tenant, $route['from']));
        } catch (TransportExceptionInterface $e) {
            $this->audit($request, $tenant, 'company.mail.test_failed', ['reason' => TenantMailer::reason($e)]);

            return back()->with('error', TenantMailer::reason($e));
        }

        Integration::query()->where('integration_type', 'smtp')->update(['is_active' => true]);
        $mailer->recordSuccess($tenant);
        $this->audit($request, $tenant, 'company.mail.tested');

        return back()->with('success', "Test sent to {$user->email}. Your customer emails now go out from {$route['from']['address']}.");
    }

    /** Stop using the company's mail server; its emails go out from RoutePilot again. */
    public function turnOffMail(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        Integration::query()->where('integration_type', 'smtp')->update(['is_active' => false]);
        $this->audit($request, $tenant, 'company.mail.turned_off');

        return back()->with('success', 'Turned off. Your emails go out from RoutePilot again.');
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
            'saved' => ! empty($config['host']),
            'active' => (bool) ($smtp?->getAttribute('is_active')) && ! empty($config['host']),
            // The last time the company's server couldn't send (its email went out from RoutePilot).
            'failure' => json_decode((string) TenantSetting::getFor($tenant->id, TenantMailer::FAILURE_KEY), true),
        ];
    }

    public function update(UpdateCompanyRequest $request, PhotoService $photos, GeocodingService $geocoder): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validated();

        $tenant->fill([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'brand_color' => $data['brand_color'],
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'reply_to_email' => $data['reply_to_email'] ?? null,
        ]);
        if (array_key_exists('report_messages', $data)) {
            TenantSetting::setFor($tenant->id, 'report_messages', $data['report_messages'] ? '1' : '0');
        }
        // tax_rate is not mass-assignable (billing field) — set deliberately.
        $oldTaxRate = round((float) $tenant->getAttribute('tax_rate'), 4);
        $newTaxRate = round(((float) $data['tax_rate_percent']) / 100, 4);
        $tenant->forceFill(['tax_rate' => $newTaxRate]);

        // Re-geocode only when the address moved (or a present address was never
        // geocoded). lat/lng are derived (not fillable); a geocode failure or a
        // cleared address leaves the rest of the save intact.
        $addressChanged = $tenant->isDirty(['address_line1', 'city', 'state', 'postal_code']);
        $tenant->save();

        if ($addressChanged || ($tenant->getAttribute('lat') === null && $tenant->formattedAddress() !== null)) {
            $address = $tenant->formattedAddress();
            $coords = $address !== null ? $geocoder->geocode($address) : null;
            $tenant->forceFill(['lat' => $coords['lat'] ?? null, 'lng' => $coords['lng'] ?? null])->save();
        }

        $logo = $data['logo'] ?? null;
        if ($logo instanceof UploadedFile) {
            $old = $tenant->getAttribute('logo_path');
            // PNG keeps a transparent logo clear of a white box on the site footer and in emails.
            $tenant->forceFill(['logo_path' => $photos->replace($logo, is_string($old) ? $old : null, 'tenants', transparent: true)])->save();
        }

        // Audit the billing-sensitive field (the tax rate flows into invoices).
        if ($oldTaxRate !== $newTaxRate) {
            $this->audit($request, $tenant, 'company.tax_rate.updated', ['from' => $oldTaxRate, 'to' => $newTaxRate]);
        }

        return back()->with('success', 'Company settings saved.');
    }

    /**
     * Record a sensitive company-settings change in the audit trail.
     *
     * @param  array<string, mixed>|null  $changes
     */
    private function audit(Request $request, Tenant $tenant, string $action, ?array $changes = null): void
    {
        AuditLog::record($request->user(), $action, $tenant, $changes);
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
