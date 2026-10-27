<?php

declare(strict_types=1);

use App\Models\Integration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantMailer;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

/** @return array<string, mixed> */
function smtpPayload(array $overrides = []): array
{
    return array_merge([
        'host' => '203.0.113.10', 'port' => 587, 'encryption' => 'tls', // a public address (smtp.example.com doesn't resolve)
        'username' => 'mailer@example.com', 'password' => 'secret-pass',
        'from_address' => 'hello@example.com', 'from_name' => 'Example Pools',
    ], $overrides);
}

test('an admin saves SMTP config, encrypted at rest', function () {
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload())->assertRedirect();

    $integration = Integration::query()->where('integration_type', 'smtp')->first();
    $config = $integration?->getAttribute('config');
    expect($config['host'])->toBe('203.0.113.10');
    expect($config['password'])->toBe('secret-pass');
});

test('a blank password keeps the stored one', function () {
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload())->assertRedirect();
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload(['password' => '', 'from_name' => 'Renamed']))->assertRedirect();

    $config = Integration::query()->where('integration_type', 'smtp')->first()?->getAttribute('config');
    expect($config['password'])->toBe('secret-pass');
    expect($config['from_name'])->toBe('Renamed');
});

test('TenantMailer uses the tenant mailer once its saved SMTP is switched on', function () {
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload())->assertRedirect();

    // Saved switched off: only the test email may use it until one gets through.
    expect(app(TenantMailer::class)->prepare($this->tenant->id)['mailer'])->toBe(config('mail.default'))
        ->and(app(TenantMailer::class)->prepare($this->tenant->id, evenIfOff: true)['mailer'])->toBe('tenant_'.$this->tenant->id);

    Integration::query()->where('integration_type', 'smtp')->update(['is_active' => true]);
    $prepared = app(TenantMailer::class)->prepare($this->tenant->id);
    expect($prepared['mailer'])->toBe('tenant_'.$this->tenant->id);
    expect($prepared['from']['address'])->toBe('hello@example.com');
});

test('TenantMailer falls back to the platform mailer with no config', function () {
    $prepared = app(TenantMailer::class)->prepare(null);
    expect($prepared['mailer'])->toBe(config('mail.default'));
    expect($prepared['from'])->toBeNull();
});

test('agents cannot edit mail settings', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->patch('/company/mail', smtpPayload())->assertForbidden();
});
