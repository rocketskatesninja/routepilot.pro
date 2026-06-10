<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChemicalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chemical_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'sell_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
