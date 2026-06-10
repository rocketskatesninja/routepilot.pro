<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['super_admin', 'tenant_admin'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Only a super-admin may target other tenants; a tenant admin is
        // limited to their own customers + agents.
        $allowed = $this->user()?->isSuperAdmin() === true
            ? ['tenants', 'agents', 'customers']
            : ['customers', 'agents'];

        return [
            'audience' => ['required', Rule::in($allowed)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }
}
