<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:restock,usage,adjustment'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
