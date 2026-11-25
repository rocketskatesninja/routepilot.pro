<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class GrantPortalRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::defaults(), 'max:255'],
        ];
    }
}
