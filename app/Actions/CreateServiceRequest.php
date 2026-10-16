<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestSubmitted;
use Illuminate\Support\Facades\Notification;

/**
 * Log a customer's request for the company to answer, and let the company's
 * admins know it's waiting in their inbox.
 */
class CreateServiceRequest
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Customer $customer): ServiceRequest
    {
        $request = ServiceRequest::create([
            'customer_id' => $customer->id,
            'pool_id' => $data['pool_id'] ?? null,
            'service_type_id' => $data['service_type_id'] ?? null,
            'type' => $data['type'],
            'message' => $data['message'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);

        $admins = User::query()
            ->where('tenant_id', $customer->getAttribute('tenant_id'))
            ->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new ServiceRequestSubmitted($request));

        return $request;
    }
}
