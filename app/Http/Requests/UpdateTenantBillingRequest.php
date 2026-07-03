<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super-admin edit of a tenant's platform-billing status from the billing
 * console: the complimentary ("free") comp + an optional reason, and manual
 * trial-date management. Trust boundary is the super-admin guard below.
 */
class UpdateTenantBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'billing_free' => ['required', 'boolean'],
            'billing_note' => ['nullable', 'string', 'max:255'],
            // Cap keeps us inside the trial_ends_at TIMESTAMP column's 2038 range.
            'trial_ends_at' => ['nullable', 'date', 'before_or_equal:2037-12-31'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'trial_ends_at.before_or_equal' => 'Trial end date must be before 2038.',
        ];
    }
}
