<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Config;

/**
 * Per-tenant outbound mail. If a tenant has configured their own SMTP
 * integration (creds encrypted at rest), this registers a runtime mailer for
 * them and returns its name + From address; otherwise it falls back to the
 * platform default mailer. Runs inside the queue worker, so it re-reads +
 * decrypts the creds each send — they are never serialized into a job payload.
 */
class TenantMailer
{
    /**
     * @return array{mailer: string, from: array{address: string, name: string}|null}
     */
    public function prepare(?int $tenantId): array
    {
        $default = (string) config('mail.default');
        if ($tenantId === null) {
            return ['mailer' => $default, 'from' => null];
        }

        $integration = Integration::query()
            ->where('tenant_id', $tenantId)
            ->where('integration_type', 'smtp')
            ->where('is_active', true)
            ->first();

        $config = $integration?->getAttribute('config');
        if (! is_array($config) || empty($config['host'])) {
            return ['mailer' => $default, 'from' => null];
        }

        $name = 'tenant_'.$tenantId;
        $encryption = $config['encryption'] ?? 'tls';
        Config::set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'host' => (string) $config['host'],
            'port' => (int) ($config['port'] ?? 587),
            'encryption' => $encryption !== '' ? (string) $encryption : null,
            'username' => isset($config['username']) ? (string) $config['username'] : null,
            'password' => isset($config['password']) ? (string) $config['password'] : null,
            'timeout' => 10,
        ]);

        $from = ! empty($config['from_address'])
            ? ['address' => (string) $config['from_address'], 'name' => (string) ($config['from_name'] ?? '')]
            : null;

        return ['mailer' => $name, 'from' => $from];
    }
}
