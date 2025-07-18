<?php

namespace App\Http\Requests;

use App\Constants\AppConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TechnicianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->route('technician')),
            ],
            'phone' => 'nullable|string|max:20',
            'street_address' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:10',
            'notes_by_admin' => 'nullable|string',
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:' . implode(',', AppConstants::ALLOWED_IMAGE_TYPES),
                'max:' . AppConstants::MAX_FILE_SIZE,
            ],
            'role' => ['required', Rule::in([AppConstants::ROLE_TECHNICIAN])],
            'is_active' => 'boolean',
            'hire_date' => 'nullable|date',
            'hourly_rate' => 'nullable|numeric|min:0',
            'specializations' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
        ];

        // Add password validation for create requests
        if ($this->isMethod('POST')) {
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            $rules['password'] = 'nullable|string|min:8|confirmed';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'profile_photo.image' => 'The profile photo must be an image.',
            'profile_photo.mimes' => 'The profile photo must be a file of type: jpeg, png, jpg, gif.',
            'profile_photo.max' => 'The profile photo may not be greater than 2MB.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.in' => 'Invalid role selected.',
            'hourly_rate.numeric' => 'Hourly rate must be a number.',
            'hourly_rate.min' => 'Hourly rate cannot be negative.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'street_address' => 'street address',
            'street_address_2' => 'street address 2',
            'zip_code' => 'zip code',
            'notes_by_admin' => 'admin notes',
            'profile_photo' => 'profile photo',
            'hire_date' => 'hire date',
            'hourly_rate' => 'hourly rate',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_phone' => 'emergency contact phone',
        ];
    }
} 