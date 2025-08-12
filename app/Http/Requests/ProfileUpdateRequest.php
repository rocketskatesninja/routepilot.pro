<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Constants\AppConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'street_address_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:' . (25 * 1024)],
            'notes_by_client' => ['nullable', 'string'],
            'monthly_billing' => ['nullable', 'boolean'],
            'service_reports' => ['nullable', Rule::in([AppConstants::REPORT_TYPE_FULL, AppConstants::REPORT_TYPE_INVOICE_ONLY, AppConstants::REPORT_TYPE_SERVICES_ONLY, AppConstants::REPORT_TYPE_NONE])],
            'service_reports_enabled' => ['nullable', 'boolean'],
            'mailing_list' => ['nullable', 'boolean'],
            'service_reminders' => ['nullable', 'boolean'],
            'appointment_reminders' => ['nullable', 'boolean'],
            'maps_provider' => $this->user()->role !== 'client' ? ['required', 'string', Rule::in(['google', 'apple', 'bing'])] : ['nullable'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
        if ($this->filled('password')) {
            $rules['current_password'] = ['required', 'current_password'];
        }
        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'monthly_billing' => $this->boolean('monthly_billing'),
            'mailing_list' => $this->boolean('mailing_list'),
            'service_reminders' => $this->boolean('service_reminders'),
            'appointment_reminders' => $this->boolean('appointment_reminders'),
            'service_reports_enabled' => $this->boolean('service_reports_enabled'),
        ]);
    }
}
