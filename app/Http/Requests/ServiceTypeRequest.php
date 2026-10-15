<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The service form, for adding (POST) and editing (PATCH) a catalog entry. */
class ServiceTypeRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:500'],
            'frequency' => ['required', Rule::in(array_keys(ServiceSubscription::FREQUENCIES))],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'tasks' => ['array', 'max:30'],
            'tasks.*' => ['nullable', 'string', 'max:255'],
            'field_modules' => ['array:'.implode(',', array_keys(ServiceType::MODULES))],
            'field_modules.*' => ['boolean'],
            'is_active' => ['boolean'],
            'requestable' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Give the service a name.',
            'price.required' => 'Set a price (0 is fine for something you don\'t charge for).',
            'estimated_duration_minutes.*' => 'Time on site should be between 5 and 600 minutes.',
        ];
    }
}
