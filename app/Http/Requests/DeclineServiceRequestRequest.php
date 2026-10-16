<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Declining a customer's request — the reason is shown to them. */
class DeclineServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reply' => ['required', 'string', 'max:1000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reply.required' => 'Tell the customer why — they\'ll see this.'];
    }
}
