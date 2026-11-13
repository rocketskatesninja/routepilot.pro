<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\EmailHtml;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === 'tenant_admin';
    }

    /** Normalize the two-letter state to uppercase before validation. */
    protected function prepareForValidation(): void
    {
        $state = $this->input('state');
        if (is_string($state)) {
            $this->merge(['state' => strtoupper(trim($state))]);
        }
        // The email signature is rich text: only the allowed tags and links get past here.
        if (is_string($this->input('email_signature'))) {
            $this->merge(['email_signature' => EmailHtml::clean($this->input('email_signature'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'brand_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:30'],
            'logo' => ['nullable', 'image', 'max:10240'],
            // Where customers' replies to the company's emails go (blank = RoutePilot's address).
            'reply_to_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            // Whether customers can message the company from their visit reports.
            'report_messages' => ['boolean'],
            // Added to emails from Email people; left empty, the default from the company's details.
            'email_signature' => ['nullable', 'string', 'max:'.EmailHtml::MAX_LENGTH],
            // Business address (optional) — but a street line requires the
            // city/state/ZIP so it can geocode.
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100', 'required_with:address_line1'],
            'state' => ['nullable', 'string', 'size:2', 'required_with:address_line1'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}(-\d{4})?$/', 'required_with:address_line1'],
        ];
    }
}
