<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'frequency' => ['required', Rule::in(array_keys(ServiceSubscription::FREQUENCIES))],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'chemicals_included' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tasks' => ['array'],
            'tasks.*' => ['nullable', 'string', 'max:255'],
            'field_modules' => ['array'],
            'field_modules.*' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
