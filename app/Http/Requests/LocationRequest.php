<?php

namespace App\Http\Requests;

use App\Constants\AppConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
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
        return [
            'client_id' => 'required|exists:clients,id',
            'nickname' => 'nullable|string|max:255',
            'street_address' => 'required|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'access' => ['nullable', Rule::in([AppConstants::ACCESS_RESIDENTIAL, AppConstants::ACCESS_COMMERCIAL])],
            'pool_type' => ['nullable', Rule::in([AppConstants::POOL_TYPE_FIBERGLASS, AppConstants::POOL_TYPE_VINYL_LINER, AppConstants::POOL_TYPE_CONCRETE, AppConstants::POOL_TYPE_GUNITE])],
            'water_type' => ['nullable', Rule::in([AppConstants::WATER_TYPE_CHLORINE, AppConstants::WATER_TYPE_SALT])],
            'filter_type' => 'nullable|string|max:255',
            'setting' => ['nullable', Rule::in([AppConstants::SETTING_INDOOR, AppConstants::SETTING_OUTDOOR])],
            'installation' => ['nullable', Rule::in([AppConstants::INSTALLATION_INGROUND, AppConstants::INSTALLATION_ABOVE])],
            'gallons' => 'nullable|integer|min:1',
            'service_frequency' => ['nullable', Rule::in([AppConstants::FREQUENCY_WEEKLY, AppConstants::FREQUENCY_BI_WEEKLY, AppConstants::FREQUENCY_MONTHLY, AppConstants::FREQUENCY_AS_NEEDED])],
            'service_day_1' => 'nullable|string|max:255',
            'service_day_2' => 'nullable|string|max:255',
            'rate_per_visit' => 'nullable|numeric|min:0',
            'chemicals_included' => 'boolean',
            'assigned_technician_id' => 'nullable|exists:users,id',
            'is_favorite' => 'boolean',
            'status' => ['nullable', Rule::in([AppConstants::STATUS_ACTIVE, AppConstants::STATUS_INACTIVE])],
            'notes' => 'nullable|string',
            // Cleaning tasks
            'vacuumed' => 'boolean',
            'brushed' => 'boolean',
            'skimmed' => 'boolean',
            'cleaned_skimmer_basket' => 'boolean',
            'cleaned_pump_basket' => 'boolean',
            'cleaned_pool_deck' => 'boolean',
            // Maintenance tasks
            'cleaned_filter_cartridge' => 'boolean',
            'backwashed_sand_filter' => 'boolean',
            'adjusted_water_level' => 'boolean',
            'adjusted_auto_fill' => 'boolean',
            'adjusted_pump_timer' => 'boolean',
            'adjusted_heater' => 'boolean',
            'checked_cover' => 'boolean',
            'checked_lights' => 'boolean',
            'checked_fountain' => 'boolean',
            'checked_heater' => 'boolean',
            // Other services
            'other_services' => 'nullable|string',
            'other_services_cost' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'chemicals_included' => $this->boolean('chemicals_included'),
            'is_favorite' => $this->boolean('is_favorite'),
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