<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'taxable' => ['boolean'],
            'occurred_on' => ['nullable', 'date'],
        ];
    }
}
