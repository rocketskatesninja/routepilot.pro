<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ServiceType;

/**
 * Update a service-type template (active/inactive retires it without delete).
 */
class UpdateServiceType
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ServiceType $serviceType, array $data): ServiceType
    {
        $serviceType->update(ServiceTypeAttributes::from($data));

        return $serviceType;
    }
}
