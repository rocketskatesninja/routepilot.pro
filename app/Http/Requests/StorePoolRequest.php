<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Customer must belong to this tenant.
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:inground,above_ground,indoor,spa,infinity,other'],
            'volume_gallons' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'surface_type' => ['nullable', 'string', 'max:120'],
            'sanitizer_type' => ['nullable', 'in:chlorine,salt,bromine,biguanide,ozone,uv,other'],
            'filter_type' => ['nullable', 'in:cartridge,sand,de'],
            'pump_type' => ['nullable', 'in:housed,external'],
            'has_heater' => ['boolean'],
            'has_automation' => ['boolean'],
            'has_pool_cleaner' => ['boolean'],
            'has_cover' => ['boolean'],
            'has_water_feature' => ['boolean'],
            'has_auto_fill' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'gate_code' => ['nullable', 'string', 'max:60'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
