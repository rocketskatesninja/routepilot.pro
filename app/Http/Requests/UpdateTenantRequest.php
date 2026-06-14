<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** Normalize the slug before validation so "My Co" / "MY_CO" land as "my-co". */
    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $ignoreId = $tenant instanceof Tenant ? $tenant->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,suspended,cancelled'],
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Tenant::RESERVED_SLUGS),
                Rule::unique('tenants', 'slug')->ignore($ignoreId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
            'slug.not_in' => 'That slug is reserved — pick another.',
            'slug.unique' => 'That slug is already taken by another company.',
        ];
    }
}
