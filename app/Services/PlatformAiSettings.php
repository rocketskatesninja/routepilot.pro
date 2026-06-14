<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * The platform's AI configuration, owned by the super-admin. AI is a bundled
 * platform feature: the provider, model, and API keys live here (DB-managed,
 * falling back to env), and tenants consume them under a per-tenant quota.
 *
 * Keys are encrypted at rest. They are NEVER returned to any UI in full — see
 * keyStatus() for the masked, safe-to-render shape.
 */
class PlatformAiSettings
{
    private const PROVIDERS = ['anthropic', 'openai'];

    public function provider(): string
    {
        $provider = PlatformSetting::get('ai_provider') ?? (string) config('ai.default_provider', 'anthropic');

        return in_array($provider, self::PROVIDERS, true) ? $provider : 'anthropic';
    }

    public function model(?string $provider = null): string
    {
        $provider ??= $this->provider();
        $model = PlatformSetting::get('ai_model');
        if ($model !== null && $model !== '') {
            return $model;
        }

        return (string) config("ai.models.{$provider}", '');
    }

    /** The usable (decrypted) API key for a provider: DB-managed first, else env. */
    public function key(?string $provider = null): string
    {
        $provider ??= $this->provider();
        $stored = PlatformSetting::get("ai_key_{$provider}");
        if ($stored !== null && $stored !== '') {
            try {
                return (string) Crypt::decryptString($stored);
            } catch (DecryptException $e) {
                Log::warning('Platform AI key failed to decrypt; falling back to env', ['provider' => $provider]);
            }
        }

        return (string) config("ai.platform_keys.{$provider}", '');
    }

    public function defaultQuota(): int
    {
        $value = PlatformSetting::get('ai_default_quota');

        return $value !== null && $value !== '' ? max(0, (int) $value) : 500;
    }

    /** Encrypt + store a provider key (empty string clears the managed key). */
    public function setKey(string $provider, string $key): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            return;
        }
        PlatformSetting::set("ai_key_{$provider}", $key === '' ? null : Crypt::encryptString($key));
    }

    /**
     * Render-safe key status per provider — whether one is configured, its
     * source, and a masked tail. Never the key itself.
     *
     * @return array<string, array{configured: bool, source: string, hint: string}>
     */
    public function keyStatus(): array
    {
        $status = [];
        foreach (self::PROVIDERS as $provider) {
            $managed = PlatformSetting::get("ai_key_{$provider}");
            $env = (string) config("ai.platform_keys.{$provider}", '');
            $source = ($managed !== null && $managed !== '') ? 'managed' : ($env !== '' ? 'env' : 'none');
            $key = $this->key($provider);
            $status[$provider] = [
                'configured' => $key !== '',
                'source' => $source,
                'hint' => $key !== '' ? '••••'.substr($key, -4) : '',
            ];
        }

        return $status;
    }
}
