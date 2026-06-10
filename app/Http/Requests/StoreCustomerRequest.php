<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'bill_chemicals' => ['boolean'],
            // Optional first pool.
            'pool_name' => ['nullable', 'string', 'max:255'],
            'pool_type' => ['nullable', 'in:inground,above_ground,indoor,spa,infinity,other'],
            'pool_volume' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'pool_sanitizer' => ['nullable', 'in:chlorine,salt,bromine,biguanide,ozone,uv,other'],
        ];
    }
}
