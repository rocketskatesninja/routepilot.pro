<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\LandingConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Gates the landing save (tenant_admin) + validates the document SHAPE. The
 * per-field content cleaning is done by LandingConfig::sanitize() in the action
 * — the real trust boundary — so the raw input is passed through, not
 * validated() (which would strip the loosely-typed section fields).
 */
class UpdateLandingRequest extends FormRequest
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
            'sections' => ['required', 'array', 'max:20'],
            'sections.*.key' => ['required', 'string', Rule::in(LandingConfig::SECTION_KEYS)],
            'sections.*.enabled' => ['required', 'boolean'],
            'seo' => ['nullable', 'array'],
            'seo.title' => ['nullable', 'string', 'max:120'],
            'seo.description' => ['nullable', 'string', 'max:300'],
            'theme' => ['nullable', 'array'],
            'title' => ['nullable', 'array'],
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'string', 'max:200'],
        ];
    }
}
