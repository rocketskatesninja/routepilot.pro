<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
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
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'technician_id' => 'required|exists:users,id',
            'service_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:service_date',
            'rate_per_visit' => 'required|numeric|min:0',
            'chemicals_cost' => 'nullable|numeric|min:0',
            'chemicals_included' => 'boolean',
            'extras_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];

        // Add status validation for update requests
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['status'] = 'required|in:draft,sent,paid,overdue,cancelled';
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'chemicals_included' => $this->boolean('chemicals_included'),
        ]);
    }
} 