<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\TenantSetting;
use App\Services\PlatformAiSettings;

/**
 * Resolve [provider, key, model] for a tenant. AI is platform-managed: the
 * super-admin owns the provider/model/key; a tenant overrides them only when
 * granted the `ai_allow_override` policy. Shared by the authed assistants and
 * the public lead-capture chatbot.
 */
class AiCredentials
{
    public function __construct(private PlatformAiSettings $platform) {}

    /** @return array{string, string, string} */
    public function for(int $tenantId): array
    {
        $provider = $this->platform->provider();
        $key = $this->platform->key($provider);
        $model = $this->platform->model($provider);

        if (TenantSetting::getFor($tenantId, 'ai_allow_override') === '1') {
            $provider = TenantSetting::getFor($tenantId, 'ai_provider') ?? $provider;
            $key = TenantSetting::getFor($tenantId, 'ai_api_key') ?? $key;
            $model = TenantSetting::getFor($tenantId, 'ai_model') ?? $model;
        }

        return [$provider, $key, $model];
    }
}
