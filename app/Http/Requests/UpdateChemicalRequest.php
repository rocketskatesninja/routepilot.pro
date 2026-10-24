<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ChemicalInventory;
use App\Support\StandardChemicals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChemicalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'tenant_admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $locked = $this->lockedChemical();

        return [
            'chemical_name' => ['required', 'string', 'max:255'],
            // One unit per chemical (gal, lbs or bag), fixed once it has stock or history.
            'unit' => ['required', Rule::in($locked !== null ? [$locked->unit] : StandardChemicals::UNITS)],
            'reorder_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cost_per_unit' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'sell_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->lockedChemical() !== null
            ? ['unit.in' => 'The unit can’t change once a chemical has stock or history. Add a new chemical for the new unit.']
            : [];
    }

    /** The chemical being edited, when its unit can no longer change (ChemicalInventory::unitLocked). */
    private function lockedChemical(): ?ChemicalInventory
    {
        $chemical = $this->route('chemical');

        return $chemical instanceof ChemicalInventory && $chemical->unitLocked() ? $chemical : null;
    }
}
