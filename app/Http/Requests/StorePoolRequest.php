<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** A new pool: everything an edit accepts, plus whose it is and an optional first service plan. */
class StorePoolRequest extends UpdatePoolRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The plan is checked like any plan when a service is picked, else ignored.
        $plan = SubscriptionRequest::planRules($this->user()?->tenant_id, $this->input('starts_on'));
        $plan = $this->filled('service_type_id') ? $plan : array_map(fn () => ['exclude'], $plan);

        return [
            ...parent::rules(),
            ...$plan,
            // Customer must belong to this tenant.
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
        ];
    }
}
