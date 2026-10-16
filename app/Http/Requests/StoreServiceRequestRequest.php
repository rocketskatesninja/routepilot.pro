<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A customer's request from the portal: a catalog service for one of their
 * pools, a pause or stop of a pool's service, or something else (free text).
 */
class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'customer';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customerId = Customer::query()->where('user_id', $this->user()?->id)->value('id');
        $tenantId = $this->user()?->tenant_id;
        $today = Tenant::localToday()->toDateString();
        $type = $this->input('type');

        $pool = [Rule::exists('pools', 'id')->where('customer_id', $customerId)->whereNull('deleted_at')];
        if (in_array($type, ['hold', 'cancel'], true)) {
            // Only a pool that has service can pause or stop it.
            $pool[] = function (string $attribute, mixed $value, Closure $fail): void {
                if (! ServiceSubscription::query()->where('pool_id', $value)->where('status', 'active')->exists()) {
                    $fail('That pool doesn\'t have service to pause or stop.');
                }
            };
        }

        return [
            'type' => ['required', Rule::in(array_keys(ServiceRequest::TYPES))],
            'service_type_id' => ['nullable', 'required_if:type,service', 'exclude_unless:type,service',
                Rule::exists('service_types', 'id')->where('tenant_id', $tenantId)->where('is_active', true)->where('requestable', true)],
            'pool_id' => ['nullable', 'required_if:type,service,hold,cancel', ...$pool],
            'preferred_date' => ['nullable', 'required_if:type,hold', 'date', 'after_or_equal:'.$today],
            'ends_on' => ['nullable', 'required_if:type,hold', 'exclude_unless:type,hold', 'date', 'after_or_equal:preferred_date'],
            'message' => ['nullable', 'required_if:type,other', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_type_id.required_if' => 'Pick a service.',
            'service_type_id.exists' => 'That service isn\'t available to request.',
            'pool_id.required_if' => 'Pick which pool.',
            'pool_id.exists' => 'Pick one of your pools.',
            'preferred_date.required_if' => 'Pick the first day to pause.',
            'preferred_date.after_or_equal' => 'Pick today or a later date.',
            'ends_on.required_if' => 'Pick the last day of the pause.',
            'ends_on.after_or_equal' => 'The pause can\'t end before it starts.',
            'message.required_if' => 'Tell us what you need.',
        ];
    }
}
