<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
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
        // A pause or stop is about one of their services; its pool comes with it.
        $planId = $data['service_subscription_id'] ?? null;
        $poolId = $planId !== null ? ServiceSubscription::query()->whereKey($planId)->value('pool_id') : ($data['pool_id'] ?? null);

        $request = new ServiceRequest([
            'customer_id' => $customer->id,
            'pool_id' => $poolId,
            'service_type_id' => $data['service_type_id'] ?? null,
            'type' => $data['type'],
            'message' => $data['message'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);
        $request->forceFill(['service_subscription_id' => $planId])->save();

        $admins = User::query()
            ->where('tenant_id', $customer->getAttribute('tenant_id'))
            ->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new ServiceRequestSubmitted($request));

        return $request;
    }
}
