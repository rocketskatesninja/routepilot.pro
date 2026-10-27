<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Integration;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Notifications\OpsAlert;
use App\Support\MailHost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Per-tenant outbound mail. If a tenant has configured their own SMTP
 * integration (creds encrypted at rest) and a test email got through, this
 * registers a runtime mailer for them and returns its name + From address;
 * otherwise it falls back to the platform default mailer. Runs inside the
 * queue worker, so it re-reads + decrypts the creds each send — they are never
 * serialized into a job payload.
 */
class TenantMailer
{
    /** The TenantSetting holding the last time the company's server failed, and why. */
    public const FAILURE_KEY = 'mail_failure';

    /**
     * @param  bool  $evenIfOff  use saved settings that aren't switched on yet (the test email)
     * @return array{mailer: string, from: array{address: string, name: string}|null}
     */
    public function prepare(?int $tenantId, bool $evenIfOff = false): array
    {
        $default = ['mailer' => (string) config('mail.default'), 'from' => null];
        if ($tenantId === null) {
            return $default;
        }

        $integration = Integration::query()
            ->where('tenant_id', $tenantId)
            ->where('integration_type', 'smtp')
            ->when(! $evenIfOff, fn ($q) => $q->where('is_active', true))
            ->first();

        // Its own address is required (a mail server rarely sends as anyone else's), and the
        // host is checked again here as well as on save: what a name resolves to can change.
        $config = $integration?->getAttribute('config');
        if (! is_array($config) || empty($config['host']) || empty($config['from_address'])
            || ! MailHost::allowed((string) $config['host'], (int) ($config['port'] ?? 587))) {
            return $default;
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
        // A long-running worker keeps each mailer it built; drop it so edited settings take effect.
        Mail::purge($name);

        return ['mailer' => $name, 'from' => [
            'address' => (string) $config['from_address'],
            'name' => (string) ($config['from_name'] ?? ''),
        ]];
    }

    /**
     * The company's server couldn't send: remember why (the Email tab shows it) and
     * tell its admins, at most once a day — an email went out from RoutePilot instead.
     */
    public function recordFailure(Tenant $tenant, Throwable $e): void
    {
        $reason = self::reason($e);
        TenantSetting::setFor($tenant->id, self::FAILURE_KEY, (string) json_encode(['at' => now()->toIso8601String(), 'reason' => $reason]));

        if (Cache::add('mail-failed:'.$tenant->id, 1, now()->addDay())) {
            Notification::send(User::adminsOf($tenant->id), new OpsAlert(
                'mail_failed',
                'Your mail server couldn’t send',
                $reason.' Your customers still got their emails, sent from RoutePilot instead.',
                '/company?tab=email',
            ));
        }
    }

    /** The company's server sent one: clear any failure it had. */
    public function recordSuccess(Tenant $tenant): void
    {
        if (TenantSetting::getFor($tenant->id, self::FAILURE_KEY) !== null) {
            TenantSetting::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('key', self::FAILURE_KEY)->delete();
            Cache::forget('mail-failed:'.$tenant->id);
        }
    }

    /** What went wrong, in words a company admin can act on. */
    public static function reason(Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            (bool) preg_match('/authenticat|username|password|535|534/i', $message) => 'Your mail server refused the username or password.',
            (bool) preg_match('/connection|timed out|refused|resolve|getaddrinfo|unreachable/i', $message) => 'RoutePilot couldn’t reach your mail server.',
            default => 'Your mail server said: '.Str::limit(trim($message), 160),
        };
    }
}
