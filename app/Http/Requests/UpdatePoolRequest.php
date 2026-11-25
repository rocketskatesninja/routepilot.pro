<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Pool;
use App\Support\Upload;
use Illuminate\Validation\Rule;

class UpdatePoolRequest extends AdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(Pool::TYPES)],
            'volume_gallons' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'surface_type' => ['nullable', 'string', 'max:120'],
            'sanitizer_type' => ['nullable', Rule::in(Pool::SANITIZERS)],
            'filter_type' => ['nullable', 'in:cartridge,sand,de'],
            'pump_type' => ['nullable', 'in:housed,external'],
            'has_heater' => ['boolean'],
            'has_automation' => ['boolean'],
            'has_pool_cleaner' => ['boolean'],
            'has_cover' => ['boolean'],
            'has_water_feature' => ['boolean'],
            'has_auto_fill' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Someone who gets a copy of every visit report (a landlord, a property manager).
            'report_cc_email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip' => ['nullable', 'string', 'max:10'],
            'gate_code' => ['nullable', 'string', 'max:60'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:10240', Upload::IMAGE_SIZE],
        ];
    }
}
