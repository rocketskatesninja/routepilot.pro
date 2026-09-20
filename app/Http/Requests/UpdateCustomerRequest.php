<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // A customer with a portal login shares one email with that login, so the
        // address is required and must stay unique among logins (their own aside).
        $customer = $this->route('customer');
        $portalUserId = $customer instanceof Customer ? $customer->user_id : null;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => $portalUserId !== null
                ? ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($portalUserId)]
                : ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'bill_chemicals' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
