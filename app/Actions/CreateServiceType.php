<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceType;

/**
 * Create a reusable service-type template (the catalog entry a pool
 * subscribes to). tenant_id is auto-filled by BelongsToTenant.
 */
class CreateServiceType
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ServiceType
    {
        return ServiceType::create(ServiceTypeAttributes::from($data));
    }
}
