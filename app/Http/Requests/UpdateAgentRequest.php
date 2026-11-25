<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Upload;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAgentRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('agent'))],
            'password' => ['nullable', 'string', Password::defaults(), 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'map_color' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'agent_plus' => ['boolean'],
            'photo' => ['nullable', 'image', 'max:10240', Upload::IMAGE_SIZE],
        ];
    }
}
