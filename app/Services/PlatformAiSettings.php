<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiUsage;
use App\Models\PlatformSetting;
use App\Models\TenantSetting;
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

        // Use the saved model unless it's blank or clearly belongs to the OTHER
        // provider (a stale value left behind when the provider was switched) —
        // then fall back to this provider's default so a mismatched combo (e.g.
        // provider=openai, model=claude-…) never reaches the API.
        if ($model !== null && $model !== '' && ! $this->modelBelongsToOtherProvider($model, $provider)) {
            return $model;
        }

        return (string) config("ai.models.{$provider}", '');
    }

    /** True when the model string clearly belongs to a provider other than $provider. */
    private function modelBelongsToOtherProvider(string $model, string $provider): bool
    {
        $m = strtolower($model);
        $looksAnthropic = str_contains($m, 'claude');
        $looksOpenai = str_starts_with($m, 'gpt') || str_starts_with($m, 'chatgpt')
            || str_starts_with($m, 'o1') || str_starts_with($m, 'o3') || str_starts_with($m, 'o4');

        return ($provider === 'openai' && $looksAnthropic)
            || ($provider === 'anthropic' && $looksOpenai);
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

    /**
     * Per-tenant AI status (settings + this month's usage), keyed by tenant id.
     *
     * @param  list<int>  $tenantIds
     * @return array<int, array{enabled: bool, allow_override: bool, quota: int|null, limit: int, used: int}>
     */
    public function tenantAi(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $settings = TenantSetting::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('key', ['ai_enabled', 'ai_allow_override', 'ai_monthly_quota'])
            ->get(['tenant_id', 'key', 'value'])
            ->groupBy('tenant_id');

        $usage = AiUsage::withoutGlobalScopes()
            ->where('period', now()->format('Y-m'))
            ->whereIn('tenant_id', $tenantIds)
            ->pluck('messages', 'tenant_id');

        $default = $this->defaultQuota();
        $out = [];
        foreach ($tenantIds as $id) {
            $s = collect($settings[$id] ?? [])->keyBy('key');
            $override = $s->get('ai_monthly_quota')?->getAttribute('value');
            $hasOverride = $override !== null && $override !== '';
            $out[$id] = [
                'enabled' => ($s->get('ai_enabled')?->getAttribute('value') ?? '1') !== '0',
                'allow_override' => $s->get('ai_allow_override')?->getAttribute('value') === '1',
                'quota' => $hasOverride ? (int) $override : null,
                'limit' => $hasOverride ? (int) $override : $default,
                'used' => (int) ($usage[$id] ?? 0),
            ];
        }

        return $out;
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
