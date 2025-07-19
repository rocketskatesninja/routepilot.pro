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
            'client_id' => 'nullable|exists:clients,id',
            'nickname' => 'nullable|string|max:255',
            'street_address' => 'required|string|max:255',
            'street_address_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:10',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'access' => ['nullable', Rule::in([AppConstants::ACCESS_RESIDENTIAL, AppConstants::ACCESS_COMMERCIAL])],
            'pool_type' => ['nullable', Rule::in([AppConstants::POOL_TYPE_FIBERGLASS, AppConstants::POOL_TYPE_VINYL_LINER, AppConstants::POOL_TYPE_CONCRETE, AppConstants::POOL_TYPE_GUNITE])],
            'water_type' => ['nullable', Rule::in([AppConstants::WATER_TYPE_CHLORINE, AppConstants::WATER_TYPE_SALT])],
            'filter_type' => 'nullable|string|max:255',
            'setting' => ['nullable', Rule::in([AppConstants::SETTING_INDOOR, AppConstants::SETTING_OUTDOOR])],
            'installation' => ['nullable', Rule::in([AppConstants::INSTALLATION_INGROUND, AppConstants::INSTALLATION_ABOVE])],
            'gallons' => 'nullable|integer|min:1',
            'service_frequency' => ['required', Rule::in([AppConstants::FREQUENCY_WEEKLY, AppConstants::FREQUENCY_BI_WEEKLY, AppConstants::FREQUENCY_MONTHLY, AppConstants::FREQUENCY_AS_NEEDED])],
            'service_day_1' => 'nullable|string|max:255',
            'service_day_2' => 'nullable|string|max:255',
            'rate_per_visit' => 'nullable|numeric|min:0',
            'chemicals_included' => 'boolean',
            'assigned_technician_id' => 'nullable|exists:users,id',
            'is_favorite' => 'boolean',
            'status' => ['nullable', Rule::in([AppConstants::STATUS_ACTIVE, AppConstants::STATUS_INACTIVE])],
            'notes' => 'nullable|string',
            // Cleaning tasks
            'vacuum' => 'boolean',
            'brush' => 'boolean',
            'skim' => 'boolean',
            'clean_skimmer_basket' => 'boolean',
            'clean_pump_basket' => 'boolean',
            'clean_pool_deck' => 'boolean',
            // Maintenance tasks
            'clean_filter_cartridge' => 'boolean',
            'backwash_sand_filter' => 'boolean',
            'adjust_water_level' => 'boolean',
            'adjust_auto_fill' => 'boolean',
            'adjust_pump_timer' => 'boolean',
            'adjust_heater' => 'boolean',
            'check_cover' => 'boolean',
            'check_lights' => 'boolean',
            'check_fountain' => 'boolean',
            'check_heater' => 'boolean',
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
            'vacuum' => $this->boolean('vacuum'),
            'brush' => $this->boolean('brush'),
            'skim' => $this->boolean('skim'),
            'clean_skimmer_basket' => $this->boolean('clean_skimmer_basket'),
            'clean_pump_basket' => $this->boolean('clean_pump_basket'),
            'clean_pool_deck' => $this->boolean('clean_pool_deck'),
            'clean_filter_cartridge' => $this->boolean('clean_filter_cartridge'),
            'backwash_sand_filter' => $this->boolean('backwash_sand_filter'),
            'adjust_water_level' => $this->boolean('adjust_water_level'),
            'adjust_auto_fill' => $this->boolean('adjust_auto_fill'),
            'adjust_pump_timer' => $this->boolean('adjust_pump_timer'),
            'adjust_heater' => $this->boolean('adjust_heater'),
            'check_cover' => $this->boolean('check_cover'),
            'check_lights' => $this->boolean('check_lights'),
            'check_fountain' => $this->boolean('check_fountain'),
            'check_heater' => $this->boolean('check_heater'),
        ]);
    }
} 