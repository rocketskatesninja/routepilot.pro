<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'brand_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:30'],
            'ai_provider' => ['required', 'in:anthropic,openai'],
            'ai_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
