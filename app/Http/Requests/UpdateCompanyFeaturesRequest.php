<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/** A company's feature switches: saved on their own, from settings or the setup checklist. */
class UpdateCompanyFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $features = array_keys(Tenant::FEATURES);
        $rules = [];
        foreach ($features as $feature) {
            $rules[$feature] = ['required_without_all:'.implode(',', array_diff($features, [$feature])), 'boolean'];
        }

        return $rules;
    }
}
