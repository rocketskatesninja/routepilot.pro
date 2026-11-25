<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\PlatformAiSettings;

/**
 * Resolve [provider, key, model] for a tenant. AI is platform-managed: the
 * super-admin owns the provider, model and key for every company. Shared by
 * the authed assistants and the public lead-capture chatbot. ($tenantId is
 * kept so a per-company choice has one place to go if it's ever wanted.)
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

        return [$provider, $key, $model];
    }
}
