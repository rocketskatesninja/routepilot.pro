<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\ServiceRequest;

/**
 * Log a homeowner request against their account for the tenant to action.
 */
class CreateServiceRequest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Customer $customer): ServiceRequest
    {
        return ServiceRequest::create([
            'customer_id' => $customer->id,
            'pool_id' => $data['pool_id'] ?? null,
            'type' => $data['type'],
            'message' => $data['message'],
            'preferred_date' => $data['preferred_date'] ?? null,
        ]);
    }
}
