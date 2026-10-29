<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\StandardChemicals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChemicalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** A part is always counted in units; only a part has a part number. */
    protected function prepareForValidation(): void
    {
        if ($this->input('kind') === 'part') {
            $this->merge(['unit' => StandardChemicals::PART_UNIT]);
        } else {
            $this->merge(['kind' => 'chemical', 'part_number' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chemical_name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:chemical,part'],
            'part_number' => ['nullable', 'string', 'max:60'],
            // One unit per chemical: gal, lbs, bag, puck or tab (a part: unit).
            'unit' => ['required', Rule::in($this->input('kind') === 'part' ? [StandardChemicals::PART_UNIT] : StandardChemicals::UNITS)],
            'current_stock' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'sell_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
