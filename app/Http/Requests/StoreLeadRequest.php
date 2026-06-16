<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'in:contact,quote,chatbot,booking'],
            // Structured metadata (quote inputs/estimate, booking preferences). Bounded for a public form.
            'details' => ['nullable', 'array', 'max:12'],
            'details.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
