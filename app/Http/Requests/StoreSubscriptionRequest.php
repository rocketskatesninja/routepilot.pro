<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'pool_id' => ['required', Rule::exists('pools', 'id')->where('tenant_id', $tenantId)],
            'service_type_id' => ['required', Rule::exists('service_types', 'id')->where('tenant_id', $tenantId)],
            // A one-person operation assigns work to the tenant_admin themselves, so admins are assignable too.
            'assigned_agent_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereIn('role', ['agent', 'tenant_admin'])],
            'frequency' => ['required', 'in:weekly,biweekly,monthly,one_time,seasonal'],
            'preferred_day' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ];
    }
}
