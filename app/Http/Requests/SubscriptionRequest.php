<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceSubscription;
use App\Models\Tenant;
use App\Support\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The service-plan form, for adding (POST) and editing (PATCH) a plan. */
class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $adding = $this->isMethod('post');

        return [
            // A plan stays on the pool it was made for; status starts as active.
            'pool_id' => $adding ? ['required', Rule::exists('pools', 'id')->where('tenant_id', $tenantId)] : ['exclude'],
            'status' => $adding ? ['exclude'] : ['required', 'in:active,paused,cancelled'],
            ...self::planRules($tenantId, $this->input('starts_on')),
            'hold_starts_at' => ['nullable', 'date', 'required_with:hold_ends_at'],
            'hold_ends_at' => ['nullable', 'date', 'required_with:hold_starts_at', 'after_or_equal:hold_starts_at'],
        ];
    }

    /**
     * Which service, who does it and when: the same rules wherever a plan is made
     * (here, and alongside a new pool in StorePoolRequest).
     *
     * @return array<string, mixed>
     */
    public static function planRules(?int $tenantId, mixed $startsOn): array
    {
        return [
            'service_type_id' => ['required', Rule::exists('service_types', 'id')->where('tenant_id', $tenantId)],
            // A one-person operation assigns work to the tenant_admin themselves, so admins are assignable too.
            'assigned_agent_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->whereIn('role', ['agent', 'tenant_admin'])],
            'frequency' => ['required', Rule::in(array_keys(ServiceSubscription::FREQUENCIES))],
            'preferred_day' => ['nullable', 'required_if:frequency,weekly,biweekly', Rule::in(array_keys(Weekday::MAP))],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'starts_on' => ['nullable', 'required_if:frequency,one_time', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:'.(is_string($startsOn) && $startsOn !== '' ? $startsOn : Tenant::localToday()->toDateString())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_type_id.required' => 'Pick which service this plan is for.',
            'preferred_day.required_if' => 'Pick which day of the week.',
            'starts_on.required_if' => 'Pick the date for this one-time job.',
            'ends_on.after_or_equal' => 'The end date can\'t be before the start date.',
            'day_of_month.integer' => 'Pick a day of the month from 1 to 31.',
            'day_of_month.min' => 'Pick a day of the month from 1 to 31.',
            'day_of_month.max' => 'Pick a day of the month from 1 to 31.',
            'hold_starts_at.required_with' => 'Give the hold a start date too.',
            'hold_ends_at.required_with' => 'Give the hold an end date too.',
            'hold_ends_at.after_or_equal' => 'The hold can\'t end before it starts.',
        ];
    }
}
