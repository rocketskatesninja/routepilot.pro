<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
        return [
            'customer_billing' => ['required_without:inventory_tracking', 'boolean'],
            'inventory_tracking' => ['required_without:customer_billing', 'boolean'],
        ];
    }
}
