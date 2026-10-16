<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    // Public form — anyone may submit (rate-limited at the route).
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $tenantId = $tenant instanceof Tenant ? $tenant->id : 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'in:contact,quote,chatbot,booking'],
            // The service they picked — only one this company offers to customers.
            'service_type_id' => ['nullable', 'integer',
                Rule::exists('service_types', 'id')->where('tenant_id', $tenantId)->where('is_active', true)->where('requestable', true)],
            // Structured metadata (quote inputs/estimate, booking preferences). Bounded for a public form.
            'details' => ['nullable', 'array', 'max:12'],
            'details.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
