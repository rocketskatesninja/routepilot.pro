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
        'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
        'username' => 'mailer@example.com', 'password' => 'secret-pass',
        'from_address' => 'hello@example.com', 'from_name' => 'Example Pools',
    ], $overrides);
}

test('an admin saves SMTP config, encrypted at rest', function () {
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload())->assertRedirect();

    $integration = Integration::query()->where('integration_type', 'smtp')->first();
    $config = $integration?->getAttribute('config');
    expect($config['host'])->toBe('smtp.example.com');
    expect($config['password'])->toBe('secret-pass');
});

test('a blank password keeps the stored one', function () {
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload())->assertRedirect();
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload(['password' => '', 'from_name' => 'Renamed']))->assertRedirect();

    $config = Integration::query()->where('integration_type', 'smtp')->first()?->getAttribute('config');
    expect($config['password'])->toBe('secret-pass');
    expect($config['from_name'])->toBe('Renamed');
});

test('TenantMailer uses the tenant mailer when SMTP is configured', function () {
    $this->actingAs($this->admin)->patch('/company/mail', smtpPayload())->assertRedirect();

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
