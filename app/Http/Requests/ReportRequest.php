<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->role === 'admin' || auth()->user()->role === 'technician';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'location_id' => 'required|exists:locations,id',
            'service_date' => 'required|date',
            'service_time' => 'required',
            'pool_gallons' => 'nullable|integer',
            // Chemistry
            'fac' => 'nullable|numeric',
            'cc' => 'nullable|numeric',
            'ph' => 'nullable|numeric',
            'alkalinity' => 'nullable|integer',
            'calcium' => 'nullable|integer',
            'salt' => 'nullable|integer',
            'cya' => 'nullable|integer',
            'tds' => 'nullable|integer',
            // Cleaning
            'vacuumed' => 'nullable|boolean',
            'brushed' => 'nullable|boolean',
            'skimmed' => 'nullable|boolean',
            'cleaned_skimmer_basket' => 'nullable|boolean',
            'cleaned_pump_basket' => 'nullable|boolean',
            'cleaned_pool_deck' => 'nullable|boolean',
            // Maintenance
            'cleaned_filter_cartridge' => 'nullable|boolean',
            'backwashed_sand_filter' => 'nullable|boolean',
            'adjusted_water_level' => 'nullable|boolean',
            'adjusted_auto_fill' => 'nullable|boolean',
            'adjusted_pump_timer' => 'nullable|boolean',
            'adjusted_heater' => 'nullable|boolean',
            'checked_cover' => 'nullable|boolean',
            'checked_lights' => 'nullable|boolean',
            'checked_fountain' => 'nullable|boolean',
            'checked_heater' => 'nullable|boolean',
            // Chemicals/services
            'chemicals_used' => 'nullable|string',
            'chemicals_cost' => 'nullable|numeric',
            'other_services' => 'nullable|string',
            'other_services_cost' => 'nullable|numeric',
            'total_cost' => 'nullable|numeric',
            // Notes/photos
            'notes_to_client' => 'nullable|string',
            'notes_to_admin' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'vacuumed' => $this->boolean('vacuumed'),
            'brushed' => $this->boolean('brushed'),
            'skimmed' => $this->boolean('skimmed'),
            'cleaned_skimmer_basket' => $this->boolean('cleaned_skimmer_basket'),
            'cleaned_pump_basket' => $this->boolean('cleaned_pump_basket'),
            'cleaned_pool_deck' => $this->boolean('cleaned_pool_deck'),
            'cleaned_filter_cartridge' => $this->boolean('cleaned_filter_cartridge'),
            'backwashed_sand_filter' => $this->boolean('backwashed_sand_filter'),
            'adjusted_water_level' => $this->boolean('adjusted_water_level'),
            'adjusted_auto_fill' => $this->boolean('adjusted_auto_fill'),
            'adjusted_pump_timer' => $this->boolean('adjusted_pump_timer'),
            'adjusted_heater' => $this->boolean('adjusted_heater'),
            'checked_cover' => $this->boolean('checked_cover'),
            'checked_lights' => $this->boolean('checked_lights'),
            'checked_fountain' => $this->boolean('checked_fountain'),
            'checked_heater' => $this->boolean('checked_heater'),
        ]);
    }
} 