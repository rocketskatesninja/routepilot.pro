<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceRequest;

/**
 * Approving a customer's request. A service request is approved with its plan's
 * schedule — the same rules as the plan form (the pool and service come from
 * the request itself). Every request can carry an optional note to the customer.
 */
class ApproveServiceRequestRequest extends SubscriptionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $request = $this->route('serviceRequest');
        $schedule = $request instanceof ServiceRequest && $request->isForService()
            ? array_intersect_key(parent::rules(), array_flip(['assigned_agent_id', 'frequency', 'preferred_day', 'day_of_month', 'starts_on', 'ends_on']))
            : [];

        return [...$schedule, 'reply' => ['nullable', 'string', 'max:1000']];
    }
}
