<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\Upload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'photo' => ['nullable', 'image', 'max:10240', Upload::IMAGE_SIZE],
            // Staff change their sign-in email on the spot (customers confirm from
            // the new inbox instead), so a signed-in session alone must not be
            // enough: it would hand the account to whoever is at the keyboard.
            'current_password' => [Rule::requiredIf($this->staffEmailChange()), 'nullable', 'current_password'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password to change your sign-in email.',
            'current_password.current_password' => 'That password is not correct.',
        ];
    }

    private function staffEmailChange(): bool
    {
        $user = $this->user();

        return ! $user->isCustomer()
            && strtolower(trim((string) $this->input('email'))) !== $user->email;
    }
}
