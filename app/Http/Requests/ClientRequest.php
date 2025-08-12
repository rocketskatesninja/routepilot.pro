<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Constants\AppConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'street_address' => 'nullable|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'notes_by_client' => 'nullable|string',
            'notes_by_admin' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:' . (25 * 1024),
            'appointment_reminders' => 'boolean',
            'mailing_list' => 'boolean',
            'monthly_billing' => 'boolean',
            'service_reports' => ['nullable', Rule::in([AppConstants::REPORT_TYPE_FULL, AppConstants::REPORT_TYPE_INVOICE_ONLY, AppConstants::REPORT_TYPE_SERVICES_ONLY, AppConstants::REPORT_TYPE_NONE])],
            'service_reports_enabled' => 'boolean',
            'status' => ['required', Rule::in([AppConstants::STATUS_ACTIVE, AppConstants::STATUS_INACTIVE])],
            'is_active' => 'boolean',
        ];

        // Password validation - required for new clients, optional for updates
        if ($this->isMethod('POST')) {
            $rules['password'] = 'required|string|min:8';
        } else {
            $rules['password'] = 'nullable|string|min:8';
        }

        // Email validation with unique rule
        $emailRule = ['required', 'email'];
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $clientId = $this->route('client')->id;
            $emailRule[] = Rule::unique('clients')->ignore($clientId);
        } else {
            $emailRule[] = 'unique:clients,email';
        }
        
        $rules['email'] = $emailRule;

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'role' => 'client', // Always set role to client for new clients
            'appointment_reminders' => $this->boolean('appointment_reminders'),
            'mailing_list' => $this->boolean('mailing_list'),
            'monthly_billing' => $this->boolean('monthly_billing'),
            'is_active' => $this->boolean('is_active'),
            'service_reports_enabled' => $this->boolean('service_reports_enabled'),
        ]);
    }
} 